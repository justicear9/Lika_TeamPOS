<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Events\PurchaseCreatedOrModified;
use App\Transaction;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;

/**
 * Transition a purchase from pending approval to received and apply stock.
 */
class PurchaseTransactionFinalizationService
{
    public function __construct(
        protected TransactionUtil $transactionUtil,
        protected ProductUtil $productUtil
    ) {}

    public function finalize(int $businessId, Transaction $purchase): void
    {
        if ($purchase->type !== 'purchase') {
            throw new \InvalidArgumentException('Invalid purchase transaction.');
        }

        $purchase->load('purchase_lines');

        $transactionBefore = $purchase->replicate();
        $beforeStatus = $purchase->status;

        $currencyDetails = $this->transactionUtil->purchaseCurrencyDetails($businessId);

        DB::transaction(function () use ($purchase, $businessId, $beforeStatus, $transactionBefore, $currencyDetails) {
            $purchase->status = 'received';
            $purchase->sub_status = null;
            $purchase->save();
            $purchase->refresh();

            foreach ($purchase->purchase_lines as $pl) {
                $this->productUtil->updateProductStock(
                    $beforeStatus,
                    $purchase,
                    $pl->product_id,
                    $pl->variation_id,
                    $pl->quantity,
                    0,
                    $currencyDetails
                );
            }

            $this->transactionUtil->adjustMappingPurchaseSellAfterEditingPurchase($beforeStatus, $purchase, null);

            $this->productUtil->adjustStockOverSelling($purchase);

            $purchaseOrderIds = $purchase->purchase_order_ids ?? [];
            if (! empty($purchaseOrderIds)) {
                $this->transactionUtil->updatePurchaseOrderStatus($purchaseOrderIds);
            }

            $this->transactionUtil->updatePaymentStatus($purchase->id, $purchase->final_total);

            $this->transactionUtil->activityLog($purchase, 'edited', $transactionBefore);

            PurchaseCreatedOrModified::dispatch($purchase);
        });
    }
}
