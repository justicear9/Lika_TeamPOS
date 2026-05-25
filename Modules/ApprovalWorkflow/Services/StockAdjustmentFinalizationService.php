<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Events\StockAdjustmentCreatedOrModified;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;

class StockAdjustmentFinalizationService
{
    public function __construct(
        protected TransactionUtil $transactionUtil,
        protected ProductUtil $productUtil
    ) {}

    public function finalize(int $businessId, Transaction $stock_adjustment): void
    {
        if ($stock_adjustment->type !== 'stock_adjustment') {
            throw new \InvalidArgumentException('Invalid stock adjustment transaction.');
        }

        $stock_adjustment->load('stock_adjustment_lines');
        $before = $stock_adjustment->replicate();

        foreach ($stock_adjustment->stock_adjustment_lines as $line) {
            $this->productUtil->decreaseProductQuantity(
                (int) $line->product_id,
                (int) $line->variation_id,
                (int) $stock_adjustment->location_id,
                (float) $line->quantity
            );
        }

        $business = [
            'id' => $businessId,
            'accounting_method' => request()->session()->get('business.accounting_method'),
            'location_id' => $stock_adjustment->location_id,
        ];
        $this->transactionUtil->mapPurchaseSell($business, $stock_adjustment->stock_adjustment_lines, 'stock_adjustment');

        $stock_adjustment->sub_status = null;
        $stock_adjustment->save();

        event(new StockAdjustmentCreatedOrModified($stock_adjustment, 'added'));

        $this->transactionUtil->activityLog($stock_adjustment, 'edited', $before);
    }
}
