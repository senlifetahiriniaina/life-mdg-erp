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
use Modules\Strategy\Models\StrategyPlan;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Onglet "Plans" — plans stratégiques du tenant avec leur score de santé
 * (StrategyPlanService::computeHealthScore(), déjà calculé côté contrôleur
 * avant d'atteindre cette classe).
 *
 * @implements WithMapping<StrategyPlan>
 */
class PlansSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param Collection<int, StrategyPlan> $plans */
    public function __construct(private readonly Collection $plans) {}

    public function collection(): Collection
    {
        return $this->plans;
    }

    public function title(): string
    {
        return 'Plans';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Nom', 'Statut', 'Score de santé (%)', 'Nombre d\'objectifs', 'Période', 'Cadre'];
    }

    public function map(mixed $plan): array
    {
        /** @var StrategyPlan $plan */
        return [
            $plan->name,
            $plan->status,
            $plan->health_score ?? 0,
            $plan->objectives_count ?? 0,
            trim(($plan->period_start ?? '').' - '.($plan->period_end ?? '')),
            $plan->framework ?? '—',
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
