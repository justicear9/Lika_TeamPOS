<?php

namespace Modules\Accounting\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Exports\AgeingReportDetailsExport;
use Modules\Accounting\Exports\AgeingReportSummaryExport;
use Modules\Accounting\Exports\PostedJournalReportExport;
use Modules\Accounting\Services\FinancialStatementsService;
use Modules\Accounting\Utils\AccountingUtil;

class ReportController extends Controller
{
    protected $accountingUtil;

    protected $businessUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(
        AccountingUtil $accountingUtil,
        BusinessUtil $businessUtil,
        ModuleUtil $moduleUtil,
        protected FinancialStatementsService $financialStatements
    ) {
        $this->accountingUtil = $accountingUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $first_account = AccountingAccount::where('business_id', $business_id)
                            ->where('status', 'active')
                            ->first();
        $ledger_url = null;
        if (! empty($first_account)) {
            $ledger_url = route('accounting.ledger', $first_account);
        }

        return view('accounting::report.index')
            ->with(compact('ledger_url'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function reportDateRange(int $business_id): array
    {
        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            return [request()->start_date, request()->end_date];
        }

        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);

        return [$fy['start'], $fy['end']];
    }

    /**
     * Trial Balance
     *
     * @return Response
     */
    public function trialBalance()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        [$start_date, $end_date] = $this->reportDateRange($business_id);
        $location_id = request()->input('location_id', null);
        $location_id = $location_id === '' ? null : $location_id;

        $accounts = AccountingAccount::join('accounting_accounts_transactions as AAT',
                                'AAT.accounting_account_id', '=', 'accounting_accounts.id')
                            ->where('business_id', $business_id)
                            ->whereDate('AAT.operation_date', '>=', $start_date)
                            ->whereDate('AAT.operation_date', '<=', $end_date)
                            ->when($location_id !== null, function ($q) use ($location_id) {
                                $q->where('AAT.location_id', $location_id);
                            })
                            ->select(
                                DB::raw("SUM(IF(AAT.type = 'credit', AAT.amount, 0)) as credit_balance"),
                                DB::raw("SUM(IF(AAT.type = 'debit', AAT.amount, 0)) as debit_balance"),
                                'accounting_accounts.id as account_id',
                                'accounting_accounts.gl_code',
                                'accounting_accounts.name'
                            )
                            ->groupBy('accounting_accounts.id', 'accounting_accounts.gl_code', 'accounting_accounts.name')
                            ->get();

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.trial_balance')
            ->with(compact('accounts', 'start_date', 'end_date', 'business_locations', 'location_id'));
    }

    /**
     * Balance Sheet
     *
     * @return Response
     */
    public function balanceSheet()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        [$start_date, $end_date] = $this->reportDateRange($business_id);
        $location_id = request()->input('location_id', null);
        $location_id = $location_id === '' ? null : $location_id;

        $balance_formula = $this->accountingUtil->balanceFormula();

        $base = function ($primaryTypes) use ($business_id, $start_date, $end_date, $balance_formula, $location_id) {
            return AccountingAccount::join('accounting_accounts_transactions as AAT',
                                    'AAT.accounting_account_id', '=', 'accounting_accounts.id')
                        ->join('accounting_account_types as AATP',
                                    'AATP.id', '=', 'accounting_accounts.account_sub_type_id')
                        ->whereDate('AAT.operation_date', '>=', $start_date)
                        ->whereDate('AAT.operation_date', '<=', $end_date)
                        ->when($location_id !== null, function ($q) use ($location_id) {
                            $q->where('AAT.location_id', $location_id);
                        })
                        ->select(
                            DB::raw($balance_formula),
                            'accounting_accounts.id as account_id',
                            'accounting_accounts.gl_code',
                            'accounting_accounts.name',
                            'AATP.name as sub_type'
                        )
                        ->where('accounting_accounts.business_id', $business_id)
                        ->whereIn('accounting_accounts.account_primary_type', $primaryTypes)
                        ->groupBy('accounting_accounts.id', 'accounting_accounts.gl_code', 'accounting_accounts.name', 'AATP.name');
        };

        $assets = $base(['asset'])->get();
        $liabilities = $base(['liability'])->get();
        $equities = $base(['equity'])->get();

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.balance_sheet')
            ->with(compact('assets', 'liabilities', 'equities', 'start_date', 'end_date', 'business_locations', 'location_id'));
    }

    public function profitAndLoss()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        [$start_date, $end_date] = $this->reportDateRange($business_id);
        $location_id = request()->input('location_id', null);
        $location_id = $location_id === '' ? null : $location_id;

        $rows = $this->financialStatements->profitAndLossRows(
            (int) $business_id,
            $start_date,
            $end_date,
            $location_id !== null ? (int) $location_id : null
        );
        $totals = $this->financialStatements->profitAndLossTotals(
            (int) $business_id,
            $start_date,
            $end_date,
            $location_id !== null ? (int) $location_id : null
        );

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.profit_loss')
            ->with(compact('rows', 'totals', 'start_date', 'end_date', 'business_locations', 'location_id'));
    }

    public function cashFlow()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        [$start_date, $end_date] = $this->reportDateRange($business_id);
        $location_id = request()->input('location_id', null);
        $location_id = $location_id === '' ? null : $location_id;

        $rows = $this->financialStatements->cashFlowDirectRows(
            (int) $business_id,
            $start_date,
            $end_date,
            $location_id !== null ? (int) $location_id : null
        );

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.cash_flow')
            ->with(compact('rows', 'start_date', 'end_date', 'business_locations', 'location_id'));
    }

    public function accountReceivableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'sell', 'contact', $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_report')
        ->with(compact('report_details', 'business_locations'));
    }

    public function accountPayableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);
        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'purchase', 'contact',
        $location_id);
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_report')
        ->with(compact('report_details', 'business_locations'));
    }

    public function accountReceivableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'sell', 'due_date',
        $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_details')
        ->with(compact('business_locations', 'report_details'));
    }

    public function accountPayableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'purchase', 'due_date',
        $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_details')
        ->with(compact('business_locations', 'report_details'));
    }

    public function postedJournal(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeReportAccess((int) $business_id);

        [$fy_start, $fy_end] = $this->reportDateRange((int) $business_id);
        $start_date = $request->input('start_date', $fy_start);
        $end_date = $request->input('end_date', $fy_end);
        $account_id = $request->input('account_id');
        $balancing_account_id = $request->input('balancing_account_id');
        $search = trim((string) $request->input('search', ''));
        $per_page = (int) $request->input('per_page', 25);
        if (! in_array($per_page, [25, 50, 100, 200], true)) {
            $per_page = 25;
        }

        $rows = $this->postedJournalBaseQuery((int) $business_id, $start_date, $end_date, $account_id, $balancing_account_id, $search)
            ->orderByDesc('AAT.operation_date')
            ->orderByDesc('AAT.id')
            ->paginate($per_page)
            ->appends($request->query());

        $accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('accounting::report.posted_journal')
            ->with(compact('rows', 'accounts', 'start_date', 'end_date', 'account_id', 'balancing_account_id', 'search', 'per_page'));
    }

    public function postedJournalExport(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeReportAccess((int) $business_id);

        [$fy_start, $fy_end] = $this->reportDateRange((int) $business_id);
        $start_date = $request->input('start_date', $fy_start);
        $end_date = $request->input('end_date', $fy_end);
        $account_id = $request->input('account_id');
        $balancing_account_id = $request->input('balancing_account_id');
        $search = trim((string) $request->input('search', ''));

        $rows = $this->postedJournalBaseQuery((int) $business_id, $start_date, $end_date, $account_id, $balancing_account_id, $search)
            ->orderByDesc('AAT.operation_date')
            ->orderByDesc('AAT.id')
            ->get();

        $filename = 'posted-journal-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new PostedJournalReportExport($rows), $filename);
    }

    public function exportAccountReceivableAgeingReport(Request $request)
    {
        return $this->exportAgeingSummary(
            $request,
            'sell',
            'account-receivable-ageing-summary',
            __('sale.customer_name')
        );
    }

    public function exportAccountPayableAgeingReport(Request $request)
    {
        return $this->exportAgeingSummary(
            $request,
            'purchase',
            'account-payable-ageing-summary',
            __('purchase.supplier')
        );
    }

    public function exportAccountReceivableAgeingDetails(Request $request)
    {
        return $this->exportAgeingDetails(
            $request,
            'sell',
            'account-receivable-ageing-details',
            __('accounting::lang.invoice'),
            __('sale.invoice_no')
        );
    }

    public function exportAccountPayableAgeingDetails(Request $request)
    {
        return $this->exportAgeingDetails(
            $request,
            'purchase',
            'account-payable-ageing-details',
            __('lang_v1.purchase'),
            __('purchase.ref_no')
        );
    }

    private function exportAgeingSummary(Request $request, string $transactionType, string $filenamePrefix, string $contactColumnHeading)
    {
        $business_id = (int) $request->session()->get('user.business_id');
        $this->authorizeReportAccess($business_id);

        $location_id = $request->input('location_id');
        $report_details = $this->accountingUtil->getAgeingReport($business_id, $transactionType, 'contact', $location_id);

        $filename = $filenamePrefix.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new AgeingReportSummaryExport($report_details, $contactColumnHeading),
            $filename
        );
    }

    private function exportAgeingDetails(
        Request $request,
        string $transactionType,
        string $filenamePrefix,
        string $transactionTypeLabel,
        string $referenceColumnHeading
    ) {
        $business_id = (int) $request->session()->get('user.business_id');
        $this->authorizeReportAccess($business_id);

        $location_id = $request->input('location_id');
        $report_details = $this->accountingUtil->getAgeingReport($business_id, $transactionType, 'due_date', $location_id);

        $filename = $filenamePrefix.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new AgeingReportDetailsExport(
                $report_details,
                $this->ageingBucketLabels(),
                $transactionTypeLabel,
                $referenceColumnHeading
            ),
            $filename
        );
    }

    /**
     * @return array<string, string>
     */
    private function ageingBucketLabels(): array
    {
        return [
            'current' => __('accounting::lang.current'),
            '1_30' => __('accounting::lang.days_past_due', ['days' => '1 - 30']),
            '31_60' => __('accounting::lang.days_past_due', ['days' => '31 - 60']),
            '61_90' => __('accounting::lang.days_past_due', ['days' => '61 - 90']),
            '>90' => __('accounting::lang.91_and_over_past_due'),
        ];
    }

    private function authorizeReportAccess(int $business_id): void
    {
        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_reports'))) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function postedJournalBaseQuery(
        int $business_id,
        ?string $start_date,
        ?string $end_date,
        $account_id,
        $balancing_account_id,
        string $search
    ) {
        $query = AccountingAccTransMapping::query()
            ->from('accounting_acc_trans_mappings as ATM')
            ->join('accounting_accounts_transactions as AAT', 'AAT.acc_trans_mapping_id', '=', 'ATM.id')
            ->join('accounting_accounts as AA', 'AA.id', '=', 'AAT.accounting_account_id')
            ->where('ATM.business_id', $business_id)
            ->whereIn('ATM.type', [
                'journal_entry',
                'fixed_asset_depreciation',
                'fixed_asset_acquisition',
                'fixed_asset_disposal',
            ])
            ->when(! empty($start_date), function ($q) use ($start_date) {
                $q->whereDate('AAT.operation_date', '>=', $start_date);
            })
            ->when(! empty($end_date), function ($q) use ($end_date) {
                $q->whereDate('AAT.operation_date', '<=', $end_date);
            })
            ->when(! empty($account_id), function ($q) use ($account_id) {
                $q->where('AAT.accounting_account_id', (int) $account_id);
            })
            ->when(! empty($balancing_account_id), function ($q) use ($balancing_account_id) {
                $q->whereExists(function ($subQuery) use ($balancing_account_id) {
                    $subQuery->select(DB::raw(1))
                        ->from('accounting_accounts_transactions as AATB')
                        ->whereColumn('AATB.acc_trans_mapping_id', 'AAT.acc_trans_mapping_id')
                        ->whereColumn('AATB.id', '!=', 'AAT.id')
                        ->where('AATB.accounting_account_id', (int) $balancing_account_id);
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($searchQ) use ($search) {
                    $like = '%'.$search.'%';
                    $searchQ->where('ATM.ref_no', 'like', $like)
                        ->orWhere('AA.name', 'like', $like)
                        ->orWhere('AA.gl_code', 'like', $like)
                        ->orWhere('AAT.note', 'like', $like)
                        ->orWhere('ATM.note', 'like', $like)
                        ->orWhereRaw("EXISTS (
                            SELECT 1
                            FROM accounting_accounts_transactions as AAT2
                            INNER JOIN accounting_accounts as AA2 ON AA2.id = AAT2.accounting_account_id
                            WHERE AAT2.acc_trans_mapping_id = AAT.acc_trans_mapping_id
                              AND AAT2.id <> AAT.id
                              AND (AA2.name LIKE ? OR AA2.gl_code LIKE ?)
                        )", [$like, $like]);
                });
            })
            ->select([
                'AAT.id',
                'AAT.operation_date',
                'ATM.ref_no',
                'AA.name as account_name',
                'AA.gl_code as account_gl_code',
                'AAT.type',
                'AAT.amount',
                'AAT.note as memo',
                'ATM.note as additional_notes',
                DB::raw("(
                    SELECT GROUP_CONCAT(DISTINCT AA2.name ORDER BY AA2.name SEPARATOR ', ')
                    FROM accounting_accounts_transactions as AAT2
                    INNER JOIN accounting_accounts as AA2 ON AA2.id = AAT2.accounting_account_id
                    WHERE AAT2.acc_trans_mapping_id = AAT.acc_trans_mapping_id
                      AND AAT2.id <> AAT.id
                ) as balancing_account"),
                DB::raw("(
                    SELECT GROUP_CONCAT(DISTINCT AA2.gl_code ORDER BY AA2.gl_code SEPARATOR ', ')
                    FROM accounting_accounts_transactions as AAT2
                    INNER JOIN accounting_accounts as AA2 ON AA2.id = AAT2.accounting_account_id
                    WHERE AAT2.acc_trans_mapping_id = AAT.acc_trans_mapping_id
                      AND AAT2.id <> AAT.id
                      AND AA2.gl_code IS NOT NULL
                      AND AA2.gl_code <> ''
                ) as balancing_gl_code"),
            ]);

        return $query;
    }
}
