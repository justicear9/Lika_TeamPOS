<?php

namespace Modules\Accounting\Console;

use App\BusinessLocation;
use App\Transaction;
use Illuminate\Console\Command;
use Modules\Accounting\Services\PurchaseLandedCostService;
use Modules\Accounting\Utils\AccountingUtil;

class RepostPurchaseMapsCommand extends Command
{
    protected $signature = 'accounting:repost-purchase-maps
                            {--business= : Required numeric business id}
                            {--year= : Optional calendar year filter (transaction_date)}
                            {--dry-run : Count candidates without reposting}';

    protected $description = 'Reallocate purchase freight to lines and repost purchase GL mappings.';

    public function handle(AccountingUtil $accountingUtil, PurchaseLandedCostService $landedCostService): int
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
            ->where('type', 'purchase')
            ->where('status', 'received')
            ->orderBy('id');

        if ($year !== null && $year !== '') {
            $q->whereYear('transaction_date', (int) $year);
        }

        $transactions = $q->get();
        if ($transactions->isEmpty()) {
            $this->warn('No matching received purchases.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Would repost '.$transactions->count().' received purchase(s).');

            return self::SUCCESS;
        }

        $reposted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($transactions as $transaction) {
            $location = BusinessLocation::find($transaction->location_id);
            $defaultMap = $location ? json_decode((string) $location->accounting_default_map, true) : [];
            $depositTo = $defaultMap['purchases']['deposit_to'] ?? null;
            $paymentAccount = $defaultMap['purchases']['payment_account'] ?? null;

            if (empty($depositTo) || empty($paymentAccount)) {
                $skipped++;
                continue;
            }

            try {
                $landedCostService->allocateShippingToPurchaseLines($transaction);

                if (! $accountingUtil->saveMap(
                    'purchase',
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

                if (! $accountingUtil->saveInventoryMapForPurchase($transaction, (int) $transaction->created_by)) {
                    $failed++;
                    $this->warn('Inventory map skipped tx '.$transaction->id.' (period locked).');
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
