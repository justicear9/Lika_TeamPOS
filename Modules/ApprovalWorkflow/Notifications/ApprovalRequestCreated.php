<?php

namespace Modules\ApprovalWorkflow\Notifications;

use App\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestCreated extends Notification
{
    use Queueable;

    public function __construct(
        public Transaction $transaction
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ref = $this->transaction->invoice_no ?: $this->transaction->ref_no;

        return [
            'title' => __('approvalworkflow::lang.notify_request_title'),
            'body' => __('approvalworkflow::lang.notify_request_body', [
                'type' => $this->transaction->type,
                'ref' => $ref ?? (string) $this->transaction->id,
            ]),
            'transaction_id' => $this->transaction->id,
            'url' => action([\Modules\ApprovalWorkflow\Http\Controllers\PendingApprovalController::class, 'index']),
        ];
    }
}
