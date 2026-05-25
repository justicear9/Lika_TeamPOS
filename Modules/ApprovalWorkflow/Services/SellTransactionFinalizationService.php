<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\NotificationUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Utils\ModuleUtil;
use App\Unit;
use Carbon\Carbon;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRequest;

/**
 * Finalize a draft sell (or sales_order) that was held for approval — mirrors core convertToInvoice flow.
 */
class SellTransactionFinalizationService
{
    public function __construct(
        protected TransactionUtil $transactionUtil,
        protected ProductUtil $productUtil,
        protected BusinessUtil $businessUtil,
        protected NotificationUtil $notificationUtil,
        protected ModuleUtil $moduleUtil
    ) {}

    public function finalize(int $businessId, Transaction $transaction): void
    {
        if (! in_array($transaction->type, ['sell', 'sales_order'], true)) {
            throw new \InvalidArgumentException('Unsupported transaction type for sell finalization.');
        }

        $transaction->load(['sell_lines', 'sell_lines.product', 'sell_lines.variations', 'contact']);

        $transaction_before = $transaction->replicate();

        $invoice_no = $this->transactionUtil->getInvoiceNumber($businessId, 'final', $transaction->location_id);

        $transaction->invoice_no = $invoice_no;
        $transaction->transaction_date = Carbon::now();
        $transaction->status = 'final';
        $transaction->sub_status = null;
        $transaction->is_quotation = 0;
        $transaction->save();

        $pendingRequest = ApprovalWorkflowRequest::where('transaction_id', $transaction->id)->first();
        $skipStock = $pendingRequest && $pendingRequest->stock_reserved;

        if (! $skipStock) {
            foreach ($transaction->sell_lines as $sell_line) {
                $decrease_qty = $sell_line->quantity;

                if ($sell_line->product->enable_stock == 1) {
                    $this->productUtil->decreaseProductQuantity(
                        $sell_line->product_id,
                        $sell_line->variation_id,
                        $transaction->location_id,
                        $decrease_qty
                    );
                }

                if ($sell_line->product->type == 'combo') {
                    $combo_variations = $sell_line->variations->combo_variations;

                    foreach ($combo_variations as $key => $value) {
                        $base_unit_multiplier = 1;

                        if (! empty($value['unit_id'])) {
                            $unit = Unit::find($value['unit_id']);
                            $base_unit_multiplier = ! empty($unit->base_unit_multiplier) ? $unit->base_unit_multiplier : $base_unit_multiplier;
                        }

                        $combo_variations[$key]['product_id'] = $sell_line->product_id;
                        $combo_variations[$key]['quantity'] = $value['quantity'] * $decrease_qty * $base_unit_multiplier;
                    }
                    $this->productUtil
                        ->decreaseProductQuantityCombo(
                            $combo_variations,
                            $transaction->location_id
                        );
                }
            }
        }

        $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

        $business_details = $this->businessUtil->getDetails($businessId);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $business = [
            'id' => $businessId,
            'accounting_method' => request()->session()->get('business.accounting_method'),
            'location_id' => $transaction->location_id,
            'pos_settings' => $pos_settings,
        ];

        $this->transactionUtil->mapPurchaseSell($business, $transaction->sell_lines, 'purchase');

        $this->notificationUtil->autoSendNotification($businessId, 'new_sale', $transaction, $transaction->contact);

        if ($transaction->type == 'sell') {
            $this->moduleUtil->getModuleData('after_sales', ['transaction' => $transaction]);
        }

        $this->transactionUtil->activityLog($transaction, 'edited', $transaction_before);
    }
}
