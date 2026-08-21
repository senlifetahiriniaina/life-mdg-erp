<?php

declare(strict_types=1);

namespace Modules\Strategy\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Onglet "KPI Sectoriels" — TextileSectorKpiService (Chantier 26 volet E),
 * les 5 indicateurs textile/EPI propres à Life MDG, en une grille
 * "Indicateur / Sous-catégorie / Valeur" plutôt qu'un WithMapping<row>
 * classique — la forme des 5 sections diffère trop (une moyenne globale,
 * une structure en %, un détail par sous-traitant, un mix par famille, un
 * écart moyen) pour un mapping ligne-à-ligne uniforme.
 */
class SectorKpiSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /** @param array<string, mixed> $sector */
    public function __construct(private readonly array $sector) {}

    public function title(): string
    {
        return 'KPI Sectoriels';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Indicateur', 'Sous-catégorie', 'Valeur'];
    }

    public function collection(): Collection
    {
        $rows = [];

        $margin = $this->sector['margin'] ?? ['overall' => [], 'by_family' => []];
        $rows[] = ['Marge moyenne (%)', 'Global', $margin['overall']['avg_margin_percent'] ?? '—'];
        foreach ($margin['by_family'] ?? [] as $family) {
            $rows[] = ['Marge moyenne (%)', $family['family'], $family['avg_margin_percent']];
        }

        $structure = $this->sector['cost_structure']['structure'] ?? [];
        foreach ($structure as $label => $percent) {
            $rows[] = ['Structure du coût de revient (%)', $label, $percent];
        }

        $leadTime = $this->sector['lead_time'] ?? ['overall' => [], 'by_subcontractor' => []];
        $rows[] = ['Délai de sous-traitance (jours)', 'Global — moyen', $leadTime['overall']['avg_lead_time_days'] ?? '—'];
        $rows[] = ['Taux de respect des délais (%)', 'Global', $leadTime['overall']['on_time_percent'] ?? '—'];
        foreach ($leadTime['by_subcontractor'] ?? [] as $sc) {
            $rows[] = ['Délai de sous-traitance (jours)', $sc['subcontractor'], $sc['avg_lead_time_days']];
            $rows[] = ['Taux de respect des délais (%)', $sc['subcontractor'], $sc['on_time_percent']];
        }

        foreach ($this->sector['production_mix'] ?? [] as $mix) {
            $rows[] = ['Mix de production (quantité)', $mix['family'], $mix['total_quantity']];
        }

        $variance = $this->sector['material_variance'] ?? [];
        $rows[] = ['Écart prix matière chiffré vs observé (%)', 'Moyenne', $variance['avg_variance_percent'] ?? '—'];

        return collect($rows);
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
