<?php

namespace Modules\ApprovalWorkflow\Notifications;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestResolved extends Notification
{
    use Queueable;

    public function __construct(
        public Transaction $transaction,
        public string $resolution
    ) {
        $this->resolution = in_array($resolution, ['approved', 'rejected'], true) ? $resolution : 'approved';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ref = $this->transaction->invoice_no ?: $this->transaction->ref_no;
        $key = $this->resolution === 'approved' ? 'notify_resolved_approved_body' : 'notify_resolved_rejected_body';

        return [
            'title' => __('approvalworkflow::lang.notify_resolved_title', ['state' => __(
                'approvalworkflow::lang.notify_state_'.$this->resolution
            )]),
            'body' => __('approvalworkflow::lang.'.$key, [
                'type' => $this->transaction->type,
                'ref' => $ref ?? (string) $this->transaction->id,
            ]),
            'transaction_id' => $this->transaction->id,
            'resolution' => $this->resolution,
            'url' => $this->urlForType(),
        ];
    }

    private function urlForType(): string
    {
        return match ($this->transaction->type) {
            'purchase' => action([\App\Http\Controllers\PurchaseController::class, 'index']),
            'stock_adjustment' => action([\App\Http\Controllers\StockAdjustmentController::class, 'index']),
            'sell', 'sales_order' => action([\App\Http\Controllers\SellController::class, 'index']),
            default => url('/home'),
        };
    }
}
