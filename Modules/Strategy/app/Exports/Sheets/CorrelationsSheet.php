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
 * Onglet "Corrélations" — CorrelationAnalysisService::topCorrelations(),
 * source réelle des lignes du cockpit (soit des Correlation calculées et
 * stockées en base, soit — quand la table est vide — la base de
 * connaissance PME pré-alimentée que le service documente lui-même comme
 * repli). Les deux formes n'ont pas exactement les mêmes clés (un
 * ::toArray() de Correlation n'a pas de colonne `interpretation`, seul le
 * repli statique en a une ; le repli utilise `lag`, la colonne réelle
 * `lag_periods`) — la carte gère les deux sans erreur.
 *
 * @implements WithMapping<array<string, mixed>>
 */
class CorrelationsSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $correlations */
    public function __construct(private readonly array $correlations) {}

    public function collection(): Collection
    {
        return collect($this->correlations);
    }

    public function title(): string
    {
        return 'Corrélations';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['KPI A', 'KPI B', 'Coefficient (r)', 'Décalage (mois)', 'Confiance (%)', 'Interprétation'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['kpi_a'] ?? '',
            $row['kpi_b'] ?? '',
            $row['coefficient'] ?? '',
            $row['lag_periods'] ?? $row['lag'] ?? 0,
            $row['confidence'] ?? '',
            $row['interpretation'] ?? '—',
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
