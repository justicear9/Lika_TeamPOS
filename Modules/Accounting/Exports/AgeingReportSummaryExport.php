<?php

namespace Modules\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AgeingReportSummaryExport implements FromArray, WithHeadings
{
    public function __construct(
        private array $reportDetails,
        private string $contactColumnHeading
    ) {
    }

    public function headings(): array
    {
        return [
            $this->contactColumnHeading,
            __('contact.pay_term'),
            __('lang_v1.current'),
            __('accounting::lang.1_30_days'),
            __('accounting::lang.31_60_days'),
            __('accounting::lang.61_90_days'),
            __('accounting::lang.91_and_over'),
            __('sale.total'),
        ];
    }

    public function array(): array
    {
        $rows = [];
        $totals = [
            '<1' => 0,
            '1_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '>90' => 0,
            'total_due' => 0,
        ];

        foreach ($this->reportDetails as $report) {
            $rows[] = [
                $report['name'] ?? '',
                $report['pay_term'] ?? '-',
                (float) ($report['<1'] ?? 0),
                (float) ($report['1_30'] ?? 0),
                (float) ($report['31_60'] ?? 0),
                (float) ($report['61_90'] ?? 0),
                (float) ($report['>90'] ?? 0),
                (float) ($report['total_due'] ?? 0),
            ];

            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) ($report[$key] ?? 0);
            }
        }

        $rows[] = [
            __('sale.total'),
            '',
            $totals['<1'],
            $totals['1_30'],
            $totals['31_60'],
            $totals['61_90'],
            $totals['>90'],
            $totals['total_due'],
        ];

        return $rows;
    }
}
