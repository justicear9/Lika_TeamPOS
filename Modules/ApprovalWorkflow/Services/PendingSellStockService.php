<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Transaction;
use App\Utils\ProductUtil;
use App\Unit;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRequest;

class PendingSellStockService
{
    public function __construct(
        protected ProductUtil $productUtil
    ) {}

    public function syncReservation(Transaction $transaction, ?ApprovalWorkflowRequest $request = null): void
    {
        if (! in_array($transaction->type, ['sell', 'sales_order'], true)) {
            return;
        }

        $request ??= ApprovalWorkflowRequest::where('transaction_id', $transaction->id)->first();
        if (! $request) {
            return;
        }

        $transaction->load(['sell_lines', 'sell_lines.product', 'sell_lines.variations']);

        $old = is_array($request->payload) ? ($request->payload['sell_reservation'] ?? null) : null;
        if (is_array($old) && $old !== []) {
            $this->releaseFromSnapshot($transaction->location_id, $old);
        }

        $new = $this->buildSnapshot($transaction);
        if ($new === []) {
            $request->stock_reserved = false;
            $p = is_array($request->payload) ? $request->payload : [];
            $p['sell_reservation'] = [];
            $request->payload = $p;
            $request->save();

            return;
        }

        foreach ($new as $row) {
            if ($row['kind'] === 'parent' && $row['enable_stock']) {
                $this->productUtil->decreaseProductQuantity(
                    (int) $row['product_id'],
                    (int) $row['variation_id'],
                    (int) $transaction->location_id,
                    (float) $row['quantity']
                );
            } elseif ($row['kind'] === 'combo' && ! empty($row['lines'])) {
                $this->productUtil->decreaseProductQuantityCombo(
                    $row['lines'],
                    (int) $transaction->location_id
                );
            }
        }

        $request->stock_reserved = true;
        $p = is_array($request->payload) ? $request->payload : [];
        $p['sell_reservation'] = $new;
        $request->payload = $p;
        $request->save();
    }

    public function releaseReservation(ApprovalWorkflowRequest $request, Transaction $transaction): void
    {
        if (! in_array($transaction->type, ['sell', 'sales_order'], true)) {
            return;
        }

        $payload = is_array($request->payload) ? $request->payload : [];
        $old = $payload['sell_reservation'] ?? [];
        if (! is_array($old) || $old === []) {
            $request->stock_reserved = false;
            $request->save();

            return;
        }

        $this->releaseFromSnapshot($transaction->location_id, $old);

        $request->stock_reserved = false;
        $p = is_array($request->payload) ? $request->payload : [];
        $p['sell_reservation'] = [];
        $request->payload = $p;
        $request->save();
    }

    private function releaseFromSnapshot(int $locationId, array $snapshot): void
    {
        foreach ($snapshot as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['kind'] ?? null) === 'parent' && ! empty($row['enable_stock'])) {
                $q = (float) ($row['quantity'] ?? 0);
                if ($q > 0) {
                    $this->productUtil->updateProductQuantity(
                        $locationId,
                        (int) $row['product_id'],
                        (int) $row['variation_id'],
                        $q,
                        0,
                        null,
                        true
                    );
                }
            } elseif (($row['kind'] ?? null) === 'combo' && ! empty($row['lines'])) {
                foreach ($row['lines'] as $line) {
                    if (empty($line['quantity'])) {
                        continue;
                    }
                    $this->productUtil->updateProductQuantity(
                        $locationId,
                        (int) $line['product_id'],
                        (int) $line['variation_id'],
                        (float) $line['quantity'],
                        0,
                        null,
                        true
                    );
                }
            }
        }
    }

    private function buildSnapshot(Transaction $transaction): array
    {
        $out = [];
        foreach ($transaction->sell_lines as $sellLine) {
            $decreaseQty = (float) $sellLine->quantity;
            if ($decreaseQty <= 0) {
                continue;
            }
            $product = $sellLine->product;
            if (! $product) {
                continue;
            }
            if ((int) $product->enable_stock === 1) {
                $out[] = [
                    'kind' => 'parent',
                    'enable_stock' => true,
                    'product_id' => $sellLine->product_id,
                    'variation_id' => $sellLine->variation_id,
                    'quantity' => $decreaseQty,
                ];
            }
            if ($product->type == 'combo' && $sellLine->variations) {
                $comboVariations = $sellLine->variations->combo_variations;
                if ($comboVariations === null) {
                    continue;
                }
                if (is_object($comboVariations) && method_exists($comboVariations, 'all')) {
                    $comboVariations = $comboVariations->all();
                }
                if (empty($comboVariations) || (is_countable($comboVariations) && count($comboVariations) < 1)) {
                    continue;
                }
                foreach ($comboVariations as $key => $value) {
                    $baseUnitMultiplier = 1.0;
                    if (! empty($value['unit_id'])) {
                        $unit = Unit::find($value['unit_id']);
                        $baseUnitMultiplier = ! empty($unit->base_unit_multiplier) ? (float) $unit->base_unit_multiplier : 1.0;
                    }
                    $comboVariations[$key]['product_id'] = $sellLine->product_id;
                    $comboVariations[$key]['quantity'] = (float) $value['quantity'] * $decreaseQty * $baseUnitMultiplier;
                }
                $out[] = [
                    'kind' => 'combo',
                    'lines' => is_array($comboVariations) ? $comboVariations : $comboVariations->all(),
                ];
            }
        }

        return $out;
    }
}
