<?php

namespace Modules\Accounting\Console;

use Illuminate\Console\Command;
use Modules\Accounting\Utils\AccountingUtil;

class CleanOrphanGlCommand extends Command
{
    protected $signature = 'accounting:clean-orphan-gl
                            {--business= : Required numeric business id}
                            {--dry-run : List orphan transaction ids without deleting}';

    protected $description = 'Remove accounting GL lines that reference deleted POS transactions.';

    public function handle(AccountingUtil $accountingUtil): int
    {
        $businessId = $this->option('business');
        if ($businessId === null || $businessId === '' || ! ctype_digit((string) $businessId)) {
            $this->error('Pass --business=<numeric id>.');

            return self::FAILURE;
        }

        $businessId = (int) $businessId;

        if ($this->option('dry-run')) {
            $accountIds = \DB::table('accounting_accounts')
                ->where('business_id', $businessId)
                ->pluck('id');

            $orphanTransactionIds = \DB::table('accounting_accounts_transactions as aat')
                ->leftJoin('transactions as t', 't.id', '=', 'aat.transaction_id')
                ->whereIn('aat.accounting_account_id', $accountIds)
                ->whereNotNull('aat.transaction_id')
                ->whereNull('aat.transaction_payment_id')
                ->whereNull('t.id')
                ->distinct()
                ->pluck('aat.transaction_id');

            if ($orphanTransactionIds->isEmpty()) {
                $this->info('No orphan GL transaction ids found.');

                return self::SUCCESS;
            }

            $this->warn('Orphan transaction ids: '.$orphanTransactionIds->implode(', '));

            return self::SUCCESS;
        }

        $result = $accountingUtil->purgeOrphanedTransactionGlLines($businessId);

        $this->info(
            'Removed '.$result['lines_removed'].' GL line(s) across '
            .$result['transaction_ids'].' deleted transaction(s).'
        );

        if ($result['skipped_locked'] > 0) {
            $this->warn($result['skipped_locked'].' transaction(s) skipped because the period is locked.');
        }

        return self::SUCCESS;
    }
}
