<?php

namespace Modules\Accounting\Services;

use App\Business;
use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;

class PurchaseLandedCostService
{
    public const ALLOCATION_TOLERANCE = 0.0001;

    public function __construct(protected ProductUtil $productUtil)
    {
    }

    /**
     * Allocate purchase shipping charges to lines by line value (landed cost).
     */
    public function allocateShippingToPurchaseLines(Transaction $transaction): void
    {
        if ($transaction->type !== 'purchase') {
            return;
        }

        $shipping = round((float) $transaction->shipping_charges, 4);
        $lines = PurchaseLine::where('transaction_id', $transaction->id)->get();

        if ($lines->isEmpty()) {
            return;
        }

        $lineData = [];
        $sumBase = 0.0;

        foreach ($lines as $line) {
            $qty = (float) $line->quantity;
            if ($qty <= self::ALLOCATION_TOLERANCE) {
                continue;
            }

            $freightAlloc = (float) ($line->freight_allocation ?? 0);
            $baseUnit = (float) $line->purchase_price_inc_tax - ($freightAlloc / $qty);
            if ($baseUnit < 0) {
                $baseUnit = (float) $line->purchase_price_inc_tax;
            }

            $baseTotal = round($qty * $baseUnit, 4);
            $lineData[$line->id] = [
                'line' => $line,
                'base_unit' => $baseUnit,
                'base_total' => $baseTotal,
            ];
            $sumBase += $baseTotal;
        }

        if (empty($lineData)) {
            return;
        }

        if ($shipping <= self::ALLOCATION_TOLERANCE || $sumBase <= self::ALLOCATION_TOLERANCE) {
            $this->clearFreightFromLines($lineData);

            return;
        }

        $allocations = [];
        $allocated = 0.0;
        $largestId = null;
        $largestTotal = -1.0;

        foreach ($lineData as $id => $data) {
            if ($data['base_total'] > $largestTotal) {
                $largestTotal = $data['base_total'];
                $largestId = $id;
            }

            $share = round($shipping * ($data['base_total'] / $sumBase), 4);
            $allocations[$id] = $share;
            $allocated += $share;
        }

        $remainder = round($shipping - $allocated, 4);
        if (abs($remainder) > self::ALLOCATION_TOLERANCE && $largestId !== null) {
            $allocations[$largestId] = round(($allocations[$largestId] ?? 0) + $remainder, 4);
        }

        foreach ($lineData as $id => $data) {
            /** @var PurchaseLine $line */
            $line = $data['line'];
            $qty = (float) $line->quantity;
            $freight = $allocations[$id] ?? 0.0;

            $line->freight_allocation = $freight;
            $line->purchase_price_inc_tax = round($data['base_unit'] + ($freight / $qty), 4);
            $line->save();
        }

        $this->syncVariationPurchasePrices($transaction, $lineData);
    }

    /**
     * @param  array<int, array{line: PurchaseLine, base_unit: float, base_total: float}>  $lineData
     */
    protected function clearFreightFromLines(array $lineData): void
    {
        foreach ($lineData as $data) {
            /** @var PurchaseLine $line */
            $line = $data['line'];
            $line->freight_allocation = 0;
            $line->purchase_price_inc_tax = round($data['base_unit'], 4);
            $line->save();
        }
    }

    /**
     * @param  array<int, array{line: PurchaseLine, base_unit: float, base_total: float}>  $lineData
     */
    protected function syncVariationPurchasePrices(Transaction $transaction, array $lineData): void
    {
        $enableEditing = (bool) Business::where('id', $transaction->business_id)
            ->value('enable_editing_product_from_purchase');

        if (! $enableEditing) {
            return;
        }

        foreach ($lineData as $data) {
            /** @var PurchaseLine $line */
            $line = $data['line']->fresh();
            if (! $line) {
                continue;
            }

            $this->productUtil->updateProductFromPurchase([
                'variation_id' => $line->variation_id,
                'pp_without_discount' => $line->purchase_price,
                'purchase_price' => $line->purchase_price,
            ]);
        }
    }
}
