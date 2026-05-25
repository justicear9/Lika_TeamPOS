<?php

namespace Modules\ApprovalWorkflow\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRule;

class SettingsController extends Controller
{
    public const TRANSACTION_TYPES = [
        'sell' => 'approvalworkflow::lang.type_sell',
        'sales_order' => 'approvalworkflow::lang.type_sales_order',
        'purchase' => 'approvalworkflow::lang.type_purchase',
        'sell_return' => 'approvalworkflow::lang.type_sell_return',
        'stock_adjustment' => 'approvalworkflow::lang.type_stock_adjustment',
    ];

    public function edit()
    {
        if (! auth()->user()->can('approvalworkflow.manage_settings')) {
            abort(403, 'Unauthorized action.');
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
            abort(404);
        }

        $business_id = (int) session()->get('user.business_id');
        $users = User::forDropdown($business_id, false, false, false, true);

        $rules = ApprovalWorkflowRule::where('business_id', $business_id)
            ->with('approvers')
            ->get()
            ->keyBy('transaction_type');

        return view('approvalworkflow::settings.edit', [
            'types' => self::TRANSACTION_TYPES,
            'users' => $users,
            'rules' => $rules,
        ]);
    }

    public function update(Request $request)
    {
        if (! auth()->user()->can('approvalworkflow.manage_settings')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) session()->get('user.business_id');
        $enabled = (array) $request->input('enabled', []);
        $approvers = (array) $request->input('approvers', []);

        DB::transaction(function () use ($business_id, $enabled, $approvers) {
            foreach (array_keys(self::TRANSACTION_TYPES) as $type) {
                $isOn = ! empty($enabled[$type]);
                $userIds = array_filter(array_map('intval', (array) ($approvers[$type] ?? [])));

                if (! $isOn) {
                    ApprovalWorkflowRule::where('business_id', $business_id)
                        ->where('transaction_type', $type)
                        ->delete();

                    continue;
                }

                if ($userIds === []) {
                    ApprovalWorkflowRule::where('business_id', $business_id)
                        ->where('transaction_type', $type)
                        ->delete();

                    continue;
                }

                $rule = ApprovalWorkflowRule::updateOrCreate(
                    [
                        'business_id' => $business_id,
                        'transaction_type' => $type,
                    ],
                    [
                        'is_enabled' => true,
                    ]
                );

                $rule->approvers()->sync($userIds);
            }
        });

        return redirect()
            ->action([self::class, 'edit'])
            ->with('status', ['success' => 1, 'msg' => __('lang_v1.updated_success')]);
    }
}
