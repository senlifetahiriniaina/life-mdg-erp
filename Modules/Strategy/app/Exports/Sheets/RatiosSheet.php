<?php

declare(strict_types=1);

namespace Modules\Strategy\Exports\Sheets;

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
 * Onglet "Ratios" — cockpit KPI/ratio, une ligne par ratio, tel que déjà
 * calculé par StrategyRatioService::allRatiosWithStatus() (RatioController).
 *
 * @implements WithMapping<array<string, mixed>>
 */
class RatiosSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $ratios */
    public function __construct(private readonly array $ratios) {}

    public function collection(): Collection
    {
        return collect($this->ratios);
    }

    public function title(): string
    {
        return 'Ratios';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Module', 'Ratio', 'Valeur actuelle', 'Unité', 'Référence secteur (médiane)', 'Statut'];
    }

    public function map(mixed $row): array
    {
        $statusLabels = ['green' => 'Bon', 'amber' => 'À surveiller', 'red' => 'Critique'];

        return [
            $row['module'] ?? '',
            $row['name'] ?? '',
            $row['current_value'] ?? '',
            $row['unit'] ?? '',
            $row['benchmark_value'] ?? '—',
            $statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? ''),
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
