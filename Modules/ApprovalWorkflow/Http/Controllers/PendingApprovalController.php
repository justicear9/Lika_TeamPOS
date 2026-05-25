<?php

namespace Modules\ApprovalWorkflow\Http\Controllers;

use App\Transaction;
use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Modules\ApprovalWorkflow\Services\ApprovalRuleService;

class PendingApprovalController extends Controller
{
    public function index()
    {
        if (! auth()->user()->can('approvalworkflow.review')) {
            abort(403, 'Unauthorized action.');
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
            abort(404);
        }

        $business_id = (int) session()->get('user.business_id');
        $pending = ApprovalRuleService::pendingSubStatus();

        $transactions = Transaction::where('business_id', $business_id)
            ->where('sub_status', $pending)
            ->orderByDesc('id')
            ->with('contact')
            ->paginate(25);

        return view('approvalworkflow::pending.index', compact('transactions'));
    }
}
