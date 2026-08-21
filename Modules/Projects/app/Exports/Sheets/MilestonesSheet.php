<?php

declare(strict_types=1);

namespace Modules\Projects\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Onglet "Jalons" — depuis
 * ProjectReportService::generateStatusReport()'s milestones array.
 *
 * @implements WithMapping<array<string, mixed>>
 */
class MilestonesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string
    {
        return 'Jalons';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Nom', 'Échéance', 'Atteint'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['name'] ?? '',
            $row['due_date'] ?? '—',
            ($row['reached'] ?? false) ? 'Oui' : 'Non',
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2E5BE8');
        $sheet->getStyle('A1:C1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}
