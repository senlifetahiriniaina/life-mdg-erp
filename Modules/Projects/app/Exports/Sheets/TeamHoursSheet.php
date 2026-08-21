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
 * Onglet "Heures équipe" — depuis
 * ProjectReportService::generateStatusReport()'s team_hours (déjà réel,
 * calculé sur ProjectTimeLog).
 *
 * @implements WithMapping<array<string, mixed>>
 */
class TeamHoursSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string
    {
        return 'Heures équipe';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Membre', 'Heures totales', 'Heures facturables', 'Taux horaire', 'Montant facturable'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['name'] ?? '',
            $row['total_hours'] ?? 0,
            $row['billable_hours'] ?? 0,
            $row['hourly_rate'] ?? '—',
            number_format((float) ($row['billable_amount'] ?? 0), 2),
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2E5BE8');
        $sheet->getStyle('A1:E1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}
