<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Transaction;
use App\User;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRule;
use Modules\ApprovalWorkflow\Notifications\ApprovalRequestCreated;
use Modules\ApprovalWorkflow\Notifications\ApprovalRequestResolved;

class ApprovalNotificationService
{
    public function notifyApproversOfNewRequest(Transaction $transaction, ApprovalWorkflowRule $rule): void
    {
        $ids = $rule->approvers->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $notification = new ApprovalRequestCreated($transaction);
        User::whereIn('id', $ids)->get()->each(fn (User $u) => $u->notify($notification));
    }

    public function notifyRequesterOfResolution(Transaction $transaction, int $userId, string $resolution): void
    {
        if ($userId < 1) {
            return;
        }
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        $user->notify(new ApprovalRequestResolved($transaction, $resolution));
    }
}
