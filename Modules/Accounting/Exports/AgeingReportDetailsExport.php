<?php

namespace Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AgeingReportDetailsExport implements FromArray, WithHeadings
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $reportDetails
     * @param  array<string, string>  $bucketLabels
     */
    public function __construct(
        private array $reportDetails,
        private array $bucketLabels,
        private string $transactionTypeLabel,
        private string $referenceColumnHeading
    ) {
    }

    public function headings(): array
    {
        return [
            __('accounting::lang.ageing_bucket'),
            __('messages.date'),
            __('account.transaction_type'),
            $this->referenceColumnHeading,
            __('contact.contact'),
            __('contact.pay_term'),
            __('lang_v1.due_date'),
            __('lang_v1.due'),
        ];
    }

    public function array(): array
    {
        $rows = [];
        $grandTotal = 0.0;

        foreach ($this->reportDetails as $bucketKey => $lines) {
            $bucketLabel = $this->bucketLabels[$bucketKey] ?? $bucketKey;
            $bucketTotal = 0.0;

            foreach ($lines as $details) {
                $due = (float) ($details['due'] ?? 0);
                $bucketTotal += $due;
                $grandTotal += $due;

                $reference = $details['invoice_no'] ?? $details['ref_no'] ?? '';

                $rows[] = [
                    $bucketLabel,
                    $details['transaction_date'] ?? '',
                    $this->transactionTypeLabel,
                    $reference,
                    $details['contact_name'] ?? '',
                    $details['pay_term'] ?? '-',
                    $details['due_date'] ?? '',
                    $due,
                ];
            }

            $rows[] = [
                __('accounting::lang.bucket_total', ['bucket' => $bucketLabel]),
                '',
                '',
                '',
                '',
                '',
                '',
                $bucketTotal,
            ];
        }

        $rows[] = [
            __('sale.total'),
            '',
            '',
            '',
            '',
            '',
            '',
            $grandTotal,
        ];

        return $rows;
    }
}
