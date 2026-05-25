<?php

namespace Modules\Accounting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PostedJournalReportExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                'date_time' => (string) $row->operation_date,
                'reference_no' => (string) $row->ref_no,
                'account_gl_code' => (string) ($row->account_gl_code ?? ''),
                'account' => (string) $row->account_name,
                'debit' => $row->type === 'debit' ? (float) $row->amount : 0,
                'credit' => $row->type === 'credit' ? (float) $row->amount : 0,
                'memo' => (string) ($row->memo ?? ''),
                'balancing_account' => (string) ($row->balancing_account ?? ''),
                'balancing_gl' => (string) ($row->balancing_gl_code ?? ''),
                'additional_notes' => (string) ($row->additional_notes ?? ''),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date/Time',
            'Reference No',
            'GL Code',
            'Account',
            'Debit',
            'Credit',
            'Memo',
            'Balancing Account',
            'Balancing GL',
            'Additional Notes',
        ];
    }
}

