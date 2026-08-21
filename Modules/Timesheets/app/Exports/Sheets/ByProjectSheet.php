<?php

declare(strict_types=1);

namespace Modules\Timesheets\Exports\Sheets;

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
 * Onglet "Par projet" — même agrégation réelle que
 * ProjectBillingService::getProjectBillingReportData()['by_project'].
 *
 * @implements WithMapping<array<string, mixed>>
 */
class ByProjectSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string
    {
        return 'Par projet';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Projet', 'Heures facturables', 'Taux horaire moyen (XOF)', 'Montant (XOF)', 'Employés', 'Saisies'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['project_name'] ?? '—',
            $row['billable_hours'] ?? 0,
            $row['avg_hourly_rate'] ?? 0,
            $row['billable_amount'] ?? 0,
            $row['employee_count'] ?? 0,
            $row['entry_count'] ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2E5BE8');
        $sheet->getStyle('A1:F1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}
