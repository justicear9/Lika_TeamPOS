<?php

namespace Modules\ApprovalWorkflow\Http\Controllers;

use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\ApprovalWorkflow\Entities\ApprovalWorkflowRequest;
use Modules\ApprovalWorkflow\Services\ApprovalAuthorization;
use Modules\ApprovalWorkflow\Services\ApprovalNotificationService;
use Modules\ApprovalWorkflow\Services\ApprovalRuleService;
use Modules\ApprovalWorkflow\Services\PendingSellStockService;
use Modules\ApprovalWorkflow\Services\PurchaseTransactionFinalizationService;
use Modules\ApprovalWorkflow\Services\SellTransactionFinalizationService;
use Modules\ApprovalWorkflow\Services\StockAdjustmentFinalizationService;

class ApprovalActionController extends Controller
{
    public function __construct(
        protected TransactionUtil $transactionUtil
    ) {}

    public function approve(Request $request, int $id)
    {
        if (! auth()->user()->can('approvalworkflow.review')) {
            abort(403, 'Unauthorized action.');
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isModuleInstalled('ApprovalWorkflow')) {
            abort(404);
        }

        $business_id = (int) session()->get('user.business_id');
        $transaction = Transaction::where('business_id', $business_id)->findOrFail($id);

        if (! ApprovalAuthorization::userCanApproveTransaction(auth()->user(), $transaction)) {
            abort(403, 'Unauthorized action.');
        }

        $req = ApprovalWorkflowRequest::where('transaction_id', $transaction->id)->first();

        if ($transaction->type === 'sell' || $transaction->type === 'sales_order') {
            $data = [
                'final_total' => $transaction->final_total,
                'contact_id' => $transaction->contact_id,
                'status' => 'final',
            ];
            $exceeded = $this->transactionUtil->isCustomerCreditLimitExeeded($data, $transaction->id);
            if ($exceeded !== false) {
                $amount = $this->transactionUtil->num_f($exceeded, true);

                return back()->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.cutomer_credit_limit_exeeded', ['credit_limit' => $amount]),
                ]);
            }

            try {
                DB::beginTransaction();
                $transaction = $transaction->fresh();
                app(SellTransactionFinalizationService::class)->finalize($business_id, $transaction);
                $this->resolveRequest($req, $request->input('note'), $transaction, 'approved');
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::emergency('ApprovalWorkflow approve sell: '.$e->getMessage());

                return back()->with('status', [
                    'success' => 0,
                    'msg' => trans('messages.something_went_wrong'),
                ]);
            }
        } elseif ($transaction->type === 'purchase') {
            try {
                DB::beginTransaction();
                $transaction = $transaction->fresh();
                app(PurchaseTransactionFinalizationService::class)->finalize($business_id, $transaction);
                $this->resolveRequest($req, $request->input('note'), $transaction, 'approved');
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::emergency('ApprovalWorkflow approve purchase: '.$e->getMessage());

                return back()->with('status', [
                    'success' => 0,
                    'msg' => $e->getMessage() ?: trans('messages.something_went_wrong'),
                ]);
            }
        } elseif ($transaction->type === 'stock_adjustment') {
            try {
                DB::beginTransaction();
                $transaction = $transaction->fresh();
                app(StockAdjustmentFinalizationService::class)->finalize($business_id, $transaction);
                $this->resolveRequest($req, $request->input('note'), $transaction, 'approved');
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::emergency('ApprovalWorkflow approve stock adjustment: '.$e->getMessage());

                return back()->with('status', [
                    'success' => 0,
                    'msg' => $e->getMessage() ?: trans('messages.something_went_wrong'),
                ]);
            }
        } elseif ($transaction->type === 'sell_return') {
            $payload = $req->payload ?? [];
            if ($payload === [] || empty($payload['products'])) {
                return back()->with('status', [
                    'success' => 0,
                    'msg' => __('approvalworkflow::lang.sell_return_payload_missing'),
                ]);
            }
            try {
                DB::beginTransaction();
                $this->transactionUtil->completeSellReturnAfterApproval(
                    $transaction->id,
                    $payload,
                    $business_id,
                    (int) auth()->id()
                );
                $transaction->refresh();
                if (class_exists(\App\Events\SalesReturnCreatedOrModified::class)) {
                    event(new \App\Events\SalesReturnCreatedOrModified('saved', $transaction->fresh()));
                }
                $this->resolveRequest($req, $request->input('note'), $transaction, 'approved');
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::emergency('ApprovalWorkflow approve sell return: '.$e->getMessage());

                return back()->with('status', [
                    'success' => 0,
                    'msg' => trans('messages.something_went_wrong'),
                ]);
            }
        } else {
            return back()->with('status', [
                'success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ]);
        }

        return redirect()
            ->action([PendingApprovalController::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => __('approvalworkflow::lang.approved_ok')]);
    }

    public function reject(Request $request, int $id)
    {
        if (! auth()->user()->can('approvalworkflow.review')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) session()->get('user.business_id');
        $transaction = Transaction::where('business_id', $business_id)->findOrFail($id);

        if (! ApprovalAuthorization::userCanApproveTransaction(auth()->user(), $transaction)) {
            abort(403, 'Unauthorized action.');
        }

        $req = ApprovalWorkflowRequest::where('transaction_id', $transaction->id)->first();

        if ($req && in_array($transaction->type, ['sell', 'sales_order'], true)) {
            app(PendingSellStockService::class)->releaseReservation($req, $transaction);
        }

        $transaction->sub_status = ApprovalRuleService::rejectedSubStatus();
        $transaction->save();

        if ($req) {
            $req->status = ApprovalWorkflowRequest::STATUS_REJECTED;
            $req->resolved_by = auth()->id();
            $req->note = $request->input('note');
            $req->resolved_at = now();
            $req->save();
            if ((int) $req->requested_by > 0) {
                app(ApprovalNotificationService::class)->notifyRequesterOfResolution(
                    $transaction,
                    (int) $req->requested_by,
                    'rejected'
                );
            }
        }

        return redirect()
            ->action([PendingApprovalController::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => __('approvalworkflow::lang.rejected_ok')]);
    }

    private function resolveRequest(?ApprovalWorkflowRequest $req, ?string $note, ?Transaction $transaction, string $resolution = 'approved'): void
    {
        if (! $req) {
            return;
        }
        $req->status = ApprovalWorkflowRequest::STATUS_APPROVED;
        $req->resolved_by = auth()->id();
        $req->note = $note;
        $req->resolved_at = now();
        if ($req->stock_reserved) {
            $p = is_array($req->payload) ? $req->payload : [];
            unset($p['sell_reservation']);
            $req->payload = $p;
            $req->stock_reserved = false;
        }
        $req->save();
        if ($transaction && (int) $req->requested_by > 0) {
            app(ApprovalNotificationService::class)->notifyRequesterOfResolution(
                $transaction,
                (int) $req->requested_by,
                $resolution
            );
        }
    }
}
