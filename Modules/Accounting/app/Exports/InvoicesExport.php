<?php

declare(strict_types=1);

namespace Modules\Accounting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Modules\Accounting\Models\Invoice;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** @implements WithMapping<Invoice> */
class InvoicesExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /** @param array<string, string> $filters */
    public function __construct(private readonly array $filters = []) {}

    public function collection(): Collection
    {
        $query = Invoice::query()->with('createdBy');

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (! empty($this->filters['type'])) {
            $query->where('type', $this->filters['type']);
        }
        if (! empty($this->filters['from'])) {
            $query->where('invoice_date', '>=', $this->filters['from']);
        }
        if (! empty($this->filters['to'])) {
            $query->where('invoice_date', '<=', $this->filters['to']);
        }

        return $query->orderByDesc('invoice_date')->get();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['#', 'Number', 'Partner', 'Type', 'Date', 'Due Date', 'Currency', 'Subtotal', 'Tax', 'Total', 'Amount Due', 'Status', 'Created By'];
    }

    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->number,
            $row->partner_name,
            $row->type,
            $row->invoice_date?->format('Y-m-d'),
            $row->due_date?->format('Y-m-d'),
            $row->currency,
            number_format((float) $row->subtotal, 2),
            number_format((float) $row->tax_amount, 2),
            number_format((float) $row->total, 2),
            number_format((float) $row->amount_due, 2),
            $row->status,
            $row->createdBy?->name ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2E5BE8');
        $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}
