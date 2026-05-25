<?php

namespace Modules\ApprovalWorkflow\Services;

use App\User;
use App\Utils\Util;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRule;

class ApprovalRuleService
{
    public function findEnabledRuleForType(int $businessId, string $transactionType): ?ApprovalWorkflowRule
    {
        $rule = ApprovalWorkflowRule::where('business_id', $businessId)
            ->where('transaction_type', $transactionType)
            ->where('is_enabled', true)
            ->with('approvers')
            ->first();

        if (! $rule || $rule->approvers->isEmpty()) {
            return null;
        }

        return $rule;
    }

    public static function pendingSubStatus(): string
    {
        return config('approvalworkflow.sub_status_pending', 'approval_pending');
    }

    public static function rejectedSubStatus(): string
    {
        return config('approvalworkflow.sub_status_rejected', 'approval_rejected');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resolveTypeFromContext(string $context, array $input): string
    {
        if (! empty($input['type'])) {
            return (string) $input['type'];
        }

        return match ($context) {
            'purchase.store', 'purchase.update' => 'purchase',
            'sell_return.store' => 'sell_return',
            'stock_adjustment.store' => 'stock_adjustment',
            'sell_pos.store', 'sell_pos.update' => 'sell',
            default => 'sell',
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function shouldApplyForSell(array $input): bool
    {
        if (! empty($input['is_suspend'])) {
            return false;
        }

        if (empty($input['status']) || $input['status'] !== 'final') {
            return false;
        }

        if (! empty($input['is_quotation']) && (int) $input['is_quotation'] === 1) {
            return false;
        }

        if (in_array(($input['sub_status'] ?? null), ['quotation', 'proforma'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function shouldApplyForPurchase(array $input): bool
    {
        return ! empty($input['type']) && $input['type'] === 'purchase';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function shouldApplyForSellReturn(): bool
    {
        // addSellReturn always finalizes; we pre-merge to draft+pending when rule exists.
        return true;
    }

    public function userCanApprove(ApprovalWorkflowRule $rule, int $userId): bool
    {
        return $rule->approvers->contains('id', $userId);
    }

    public function shouldBypassForUser(int $userId, int $businessId, ?ApprovalWorkflowRule $rule): bool
    {
        if (! $rule) {
            return true;
        }

        $user = User::find($userId);
        if (! $user) {
            return false;
        }

        if (app(Util::class)->is_admin($user, $businessId)) {
            return true;
        }

        if ($this->userCanApprove($rule, $userId)) {
            return true;
        }

        return false;
    }
}
