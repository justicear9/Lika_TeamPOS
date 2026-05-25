<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Transaction;
use App\User;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Support\Facades\Schema;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRule;

class ApprovalAuthorization
{
    public static function userCanApproveTransaction(?User $user, Transaction $transaction): bool
    {
        if (! $user) {
            return false;
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow') || ! Schema::hasTable('approval_workflow_rules')) {
            return false;
        }

        $pending = config('approvalworkflow.sub_status_pending', 'approval_pending');
        if (($transaction->sub_status ?? null) !== $pending) {
            return false;
        }

        $rule = ApprovalWorkflowRule::where('business_id', $transaction->business_id)
            ->where('transaction_type', $transaction->type)
            ->where('is_enabled', true)
            ->with('approvers')
            ->first();

        if (! $rule || $rule->approvers->isEmpty()) {
            return false;
        }

        if (app(Util::class)->is_admin($user, (int) $transaction->business_id)) {
            return true;
        }

        return $rule->approvers->contains('id', $user->id);
    }
}
