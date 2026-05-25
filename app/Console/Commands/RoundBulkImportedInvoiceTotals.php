<?php

namespace App\Console\Commands;

use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rounds transaction, line, and payment money fields to 2 decimal places for a range
 * of bulk-imported sales invoices. Recalculates payment_status after updates.
 */
class RoundBulkImportedInvoiceTotals extends Command
{
    protected $signature = 'invoices:round-bulk-totals
                            {--business= : Business id (required in non-interactive use)}
                            {--from-invoice=0000001 : First invoice number (inclusive)}
                            {--to-invoice=0000402 : Last invoice number (inclusive)}
                            {--from-date=2026-01-22 : First transaction date (Y-m-d)}
                            {--to-date=2026-04-17 : Last transaction date (Y-m-d)}
                            {--import-only : Only rows with import_batch not null}
                            {--dry-run : Show what would change without saving}
                            {--list-each : With --dry-run, list every matching invoice}';

    protected $description = 'Round imported sell invoice totals (2dp) and refresh payment status for a given invoice/date range.';

    /** @var string[] */
    private const TRANSACTION_MONEY_COLUMNS = [
        'total_before_tax',
        'tax_amount',
        'discount_amount',
        'shipping_charges',
        'final_total',
        'round_off_amount',
        'rp_redeemed_amount',
        'additional_expense_value_1',
        'additional_expense_value_2',
        'additional_expense_value_3',
        'additional_expense_value_4',
        'packing_charge',
        'total_amount_recovered',
    ];

    /** @var string[] */
    private const SELL_LINE_MONEY_COLUMNS = [
        'unit_price',
        'unit_price_inc_tax',
        'unit_price_before_discount',
        'item_tax',
        'line_discount_amount',
    ];

    public function handle(TransactionUtil $transactionUtil): int
    {
        $businessId = $this->option('business');
        if ($businessId === null || $businessId === '') {
            $this->error('Pass --business=ID (your tenant business_id).');

            return self::FAILURE;
        }
        $businessId = (int) $businessId;

        $fromInvoice = (string) $this->option('from-invoice');
        $toInvoice = (string) $this->option('to-invoice');
        $fromDate = Carbon::parse($this->option('from-date'))->startOfDay();
        $toDate = Carbon::parse($this->option('to-date'))->endOfDay();
        $importOnly = (bool) $this->option('import-only');
        $dryRun = (bool) $this->option('dry-run');
        $listEach = (bool) $this->option('list-each');

        $this->line('DB: '.config('database.connections.mysql.database').' | business_id='.$businessId);
        $this->line("Invoices: {$fromInvoice} … {$toInvoice} | dates: {$fromDate->toDateTimeString()} … {$toDate->toDateTimeString()}");
        if ($importOnly) {
            $this->line('Filter: import_batch IS NOT NULL');
        }
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $query = Transaction::query()
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('business_id', $businessId)
            ->where('invoice_no', '>=', $fromInvoice)
            ->where('invoice_no', '<=', $toInvoice)
            ->where('transaction_date', '>=', $fromDate)
            ->where('transaction_date', '<=', $toDate)
            ->orderBy('id');

        if ($importOnly) {
            $query->whereNotNull('import_batch');
        }

        $ids = $query->pluck('id');
        $this->info('Matching transactions: '.$ids->count());

        if ($ids->isEmpty()) {
            return self::SUCCESS;
        }

        $updated = 0;
        $paymentFixed = 0;
        $linesTouched = 0;
        $txMoneyChanges = 0;
        $sampleInvoices = [];

        foreach ($ids as $id) {
            $transaction = Transaction::find($id);
            if (! $transaction) {
                continue;
            }

            if ($dryRun) {
                $changed = $this->diffMoneyRound($transaction, self::TRANSACTION_MONEY_COLUMNS);
                if (! empty($changed)) {
                    $txMoneyChanges++;
                }
                if ($listEach) {
                    if (! empty($changed)) {
                        $this->line("TX {$transaction->id} ({$transaction->invoice_no}): would round ".count($changed).' transaction money field(s).');
                    }
                    $lineCount = TransactionSellLine::where('transaction_id', $id)->count();
                    if ($lineCount) {
                        $this->line("  … {$lineCount} sell line(s) checked for price/tax rounding.");
                    }
                    $payCount = TransactionPayment::where('transaction_id', $id)->count();
                    if ($payCount) {
                        $this->line("  … {$payCount} payment(s).");
                    }
                } elseif (count($sampleInvoices) < 5 && ! empty($changed)) {
                    $sampleInvoices[] = $transaction->invoice_no.' (tx '.$transaction->id.', '.count($changed).' fields)';
                }
                $updated++;

                continue;
            }

            DB::transaction(function () use ($id, $transaction, $transactionUtil, &$linesTouched, &$paymentFixed) {
                $t = Transaction::lockForUpdate()->find($id);
                if (! $t) {
                    return;
                }

                foreach (self::TRANSACTION_MONEY_COLUMNS as $col) {
                    if (! $this->hasAttribute($t, $col)) {
                        continue;
                    }
                    $val = $t->{$col};
                    if ($val === null) {
                        continue;
                    }
                    $rounded = $this->round2($val);
                    if ((string) $rounded !== (string) $val) {
                        $t->{$col} = $rounded;
                    }
                }
                $t->save();

                $sellLines = TransactionSellLine::where('transaction_id', $id)->get();
                foreach ($sellLines as $line) {
                    $dirty = false;
                    foreach (self::SELL_LINE_MONEY_COLUMNS as $col) {
                        if (! $this->hasAttribute($line, $col)) {
                            continue;
                        }
                        $val = $line->{$col};
                        if ($val === null) {
                            continue;
                        }
                        $rounded = $this->round2($val);
                        if ((string) $rounded !== (string) $val) {
                            $line->{$col} = $rounded;
                            $dirty = true;
                        }
                    }
                    if ($dirty) {
                        $line->save();
                        $linesTouched++;
                    }
                }

                $payments = TransactionPayment::where('transaction_id', $id)->get();
                foreach ($payments as $payment) {
                    if ($payment->amount === null) {
                        continue;
                    }
                    $rounded = $this->round2($payment->amount);
                    if ((string) $rounded !== (string) $payment->amount) {
                        $payment->amount = $rounded;
                        $payment->save();
                        $paymentFixed++;
                    }
                }

                $transactionUtil->updatePaymentStatus($id, $t->final_total);
            });

            $updated++;
        }

        if ($dryRun) {
            $this->info("Dry run: {$ids->count()} sell invoice(s) in range; {$txMoneyChanges} have transaction money values that are not 2dp.");
            if (! $listEach && ! empty($sampleInvoices)) {
                $this->line('Examples: '.implode('; ', $sampleInvoices));
            }
            $this->line('Run without --dry-run to apply rounding, sell-line price rounding, payment rounding, and payment_status refresh.');
        } else {
            $this->info("Done. Transactions processed: {$updated}. Sell lines with money edits: {$linesTouched}. Payment rows rounded: {$paymentFixed}.");
        }

        return self::SUCCESS;
    }

    private function round2($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function hasAttribute(object $model, string $key): bool
    {
        return array_key_exists($key, $model->getAttributes());
    }

    /**
     * @return array<string, array{before: mixed, after: string}>
     */
    private function diffMoneyRound(Transaction $transaction, array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if (! $this->hasAttribute($transaction, $col)) {
                continue;
            }
            $val = $transaction->{$col};
            if ($val === null) {
                continue;
            }
            $after = $this->round2($val);
            if ((string) $after !== (string) $val) {
                $out[$col] = ['before' => $val, 'after' => $after];
            }
        }

        return $out;
    }
}
