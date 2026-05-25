<?php

namespace Modules\ApprovalWorkflow\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Menu;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRequest;
use Modules\ApprovalWorkflow\Services\ApprovalNotificationService;
use Modules\ApprovalWorkflow\Services\ApprovalRuleService;
use Modules\ApprovalWorkflow\Services\PendingSellStockService;

class DataController extends Controller
{
    public function user_permissions()
    {
        return [
            [
                'value' => 'approvalworkflow.manage_settings',
                'label' => __('approvalworkflow::lang.permission_manage_settings'),
                'default' => false,
            ],
            [
                'value' => 'approvalworkflow.review',
                'label' => __('approvalworkflow::lang.permission_review'),
                'default' => false,
            ],
        ];
    }

    public function superadmin_package()
    {
        return [
            [
                'name' => 'approval_workflow',
                'label' => __('approvalworkflow::lang.superadmin_package'),
                'default' => false,
            ],
        ];
    }

    public function modifyAdminMenu()
    {
        $moduleUtil = new ModuleUtil();
        if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
            return;
        }

        if (! auth()->check()) {
            return;
        }

        if (auth()->user()->can('approvalworkflow.manage_settings') || auth()->user()->can('approvalworkflow.review')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->dropdown(
                    __('approvalworkflow::lang.menu_approval_workflow'),
                    function ($sub) {
                        if (auth()->user()->can('approvalworkflow.review')) {
                            $sub->url(
                                action([\Modules\ApprovalWorkflow\Http\Controllers\PendingApprovalController::class, 'index']),
                                __('approvalworkflow::lang.pending_approvals'),
                                ['icon' => '', 'active' => request()->routeIs('approvalworkflow.pending.*')]
                            );
                        }
                        if (auth()->user()->can('approvalworkflow.manage_settings')) {
                            $sub->url(
                                action([\Modules\ApprovalWorkflow\Http\Controllers\SettingsController::class, 'edit']),
                                __('approvalworkflow::lang.settings'),
                                ['icon' => '', 'active' => request()->routeIs('approvalworkflow.settings.*')]
                            );
                        }
                    },
                    ['icon' => 'fas fa-clipboard-check', 'active' => request()->is('approval-workflow*')]
                );
            });
        }
    }

    /**
     * @param  array{context?: string, input?: array, request?: \Illuminate\Http\Request, business_id?: int}  $args
     * @return ?array{input: array<string, mixed>}
     */
    public function mutate_transaction_input_for_approval(array $args)
    {
        try {
            $moduleUtil = new ModuleUtil();
            if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
                return null;
            }
            if (! Schema::hasTable('approval_workflow_rules')) {
                return null;
            }

            $businessId = (int) ($args['business_id'] ?? 0);
            if ($businessId < 1) {
                return null;
            }

            $context = (string) ($args['context'] ?? '');
            $input = $args['input'] ?? [];

            $rules = app(ApprovalRuleService::class);
            $type = $rules->resolveTypeFromContext($context, $input);
            $rule = $rules->findEnabledRuleForType($businessId, $type);
            if (! $rule) {
                return null;
            }

            $userId = (int) (auth()->id() ?? 0);
            if ($userId < 1 || $rules->shouldBypassForUser($userId, $businessId, $rule)) {
                return null;
            }

            $pending = ApprovalRuleService::pendingSubStatus();

            if (in_array($context, ['sell_pos.store', 'sell_pos.update'], true)) {
                if (! $rules->shouldApplyForSell($input)) {
                    return null;
                }

                return [
                    'input' => [
                        'status' => 'draft',
                        'sub_status' => $pending,
                    ],
                ];
            }

            if ($context === 'purchase.store') {
                if (! $rules->shouldApplyForPurchase($input)) {
                    return null;
                }

                return [
                    'input' => [
                        'status' => 'pending',
                        'sub_status' => $pending,
                    ],
                ];
            }

            if ($context === 'sell_return.store') {
                if (! $rules->findEnabledRuleForType($businessId, 'sell_return')) {
                    return null;
                }

                return [
                    'input' => [
                        'status' => 'draft',
                        'sub_status' => $pending,
                    ],
                ];
            }

            if ($context === 'stock_adjustment.store') {
                if (! $rules->findEnabledRuleForType($businessId, 'stock_adjustment')) {
                    return null;
                }

                return [
                    'input' => [
                        'sub_status' => $pending,
                    ],
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('ApprovalWorkflow mutate_transaction_input_for_approval: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @param  array{transaction?: \App\Transaction, input?: array}|null  $data
     */
    public function after_sale_saved($data = null)
    {
        $this->createPendingRequest($data, 'after_sale');

        return null;
    }

    /**
     * @param  array{transaction?: \App\Transaction, input?: array}|null  $data
     */
    public function after_purchase_saved($data = null)
    {
        $this->createPendingRequest($data, 'after_purchase');

        return null;
    }

    /**
     * @param  array{transaction?: \App\Transaction, input?: array}|null  $data
     */
    public function after_stock_adjustment_saved($data = null)
    {
        $this->createPendingRequest($data, 'after_stock_adjustment');

        return null;
    }

    /**
     * @param  array{transaction?: \App\Transaction, input?: array}|null  $data
     */
    public function after_sales_return($data = null)
    {
        try {
            $moduleUtil = new ModuleUtil();
            if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
                return null;
            }
            if (! Schema::hasTable('approval_workflow_requests')) {
                return null;
            }
            $transaction = $data['transaction'] ?? null;
            $input = $data['input'] ?? [];
            if (! $transaction || $transaction->type !== 'sell_return') {
                return null;
            }
            $pending = ApprovalRuleService::pendingSubStatus();
            if (($transaction->sub_status ?? null) !== $pending) {
                return null;
            }
            $this->createPendingRequest(
                ['transaction' => $transaction, 'input' => $input, 'payload' => $input],
                'after_sales_return'
            );
        } catch (\Throwable $e) {
            \Log::warning('ApprovalWorkflow after_sales_return: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @param  array{transaction?: \App\Transaction, input?: array}|null  $data
     */
    private function createPendingRequest($data, string $source): void
    {
        try {
            $moduleUtil = new ModuleUtil();
            if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
                return;
            }
            if (! Schema::hasTable('approval_workflow_requests')) {
                return;
            }
            $transaction = $data['transaction'] ?? null;
            if (! $transaction) {
                return;
            }
            $pending = ApprovalRuleService::pendingSubStatus();
            if (($transaction->sub_status ?? null) !== $pending) {
                return;
            }
            $type = (string) $transaction->type;
            $rule = app(ApprovalRuleService::class)->findEnabledRuleForType((int) $transaction->business_id, $type);
            if (! $rule) {
                return;
            }
            $incomingPayload = $data['payload'] ?? null;

            $req = ApprovalWorkflowRequest::firstOrNew(
                ['transaction_id' => $transaction->id]
            );
            $isNew = ! $req->exists;
            if ($isNew) {
                $req->business_id = $transaction->business_id;
                $req->rule_id = $rule->id;
                $req->status = ApprovalWorkflowRequest::STATUS_PENDING;
                $req->requested_by = $transaction->created_by ?? auth()->id();
            } else {
                $req->rule_id = $rule->id;
                $req->status = ApprovalWorkflowRequest::STATUS_PENDING;
            }
            if (is_array($incomingPayload)) {
                $req->payload = $incomingPayload;
            }
            $req->save();

            if ($isNew) {
                app(ApprovalNotificationService::class)->notifyApproversOfNewRequest($transaction, $rule);
            }

            if (in_array($type, ['sell', 'sales_order'], true)) {
                app(PendingSellStockService::class)->syncReservation($transaction, $req);
            }
        } catch (\Throwable $e) {
            \Log::warning("ApprovalWorkflow createPendingRequest ({$source}): ".$e->getMessage());
        }
    }
}
