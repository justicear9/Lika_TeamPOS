<?php

namespace Modules\Accounting\Console;

use App\BusinessLocation;
use App\Transaction;
use Illuminate\Console\Command;
use Modules\Accounting\Utils\AccountingUtil;

class RepostSellMapsCommand extends Command
{
    protected $signature = 'accounting:repost-sell-maps
                            {--business= : Required numeric business id}
                            {--year= : Optional calendar year filter (transaction_date)}
                            {--dry-run : Count candidates without reposting}';

    protected $description = 'Repost sell invoice GL mappings so net revenue matches final_total exactly.';

    public function handle(AccountingUtil $accountingUtil): int
    {
        $businessId = $this->option('business');
        if ($businessId === null || $businessId === '' || ! ctype_digit((string) $businessId)) {
            $this->error('Pass --business=<numeric id>.');

            return self::FAILURE;
        }

        $businessId = (int) $businessId;
        $year = $this->option('year');

        $q = Transaction::query()
            ->where('business_id', $businessId)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->orderBy('id');

        if ($year !== null && $year !== '') {
            $q->whereYear('transaction_date', (int) $year);
        }

        $transactions = $q->get();
        if ($transactions->isEmpty()) {
            $this->warn('No matching final sales.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Would repost '.$transactions->count().' final sale(s).');

            return self::SUCCESS;
        }

        $reposted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($transactions as $transaction) {
            $location = BusinessLocation::find($transaction->location_id);
            $defaultMap = $location ? json_decode((string) $location->accounting_default_map, true) : [];
            $depositTo = $defaultMap['sale']['deposit_to'] ?? null;
            $paymentAccount = $defaultMap['sale']['payment_account'] ?? null;

            if (empty($depositTo) || empty($paymentAccount)) {
                $skipped++;
                continue;
            }

            try {
                if (! $accountingUtil->saveMap(
                    'sell',
                    (int) $transaction->id,
                    (int) $transaction->created_by,
                    $businessId,
                    (int) $depositTo,
                    (int) $paymentAccount
                )) {
                    $failed++;
                    $this->warn('Skipped tx '.$transaction->id.' (period locked).');
                    continue;
                }

                $reposted++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error('Failed tx '.$transaction->id.': '.$e->getMessage());
            }
        }

        $this->info("Reposted {$reposted}, skipped {$skipped} (no mapping), failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
