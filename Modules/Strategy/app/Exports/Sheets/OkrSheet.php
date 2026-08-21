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
 * Onglet "OKR" — arbre objectif → résultats clés de OkrService::getOkrTree()
 * (déjà utilisé par le cockpit et la page Objectives/Index.vue), aplati en
 * une ligne par objectif et une ligne par résultat clé sous chaque objectif.
 *
 * @implements WithMapping<array<string, mixed>>
 */
class OkrSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array{plan_id?: int|null, plan_name?: string|null, objectives?: array<int, array<string, mixed>>} $okrTree */
    public function __construct(private readonly array $okrTree) {}

    public function collection(): Collection
    {
        return collect($this->flatten($this->okrTree['objectives'] ?? []));
    }

    public function title(): string
    {
        return 'OKR';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Type', 'Titre', 'Niveau / Statut', 'Progression (%)', 'Détail'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['type'],
            $row['title'],
            $row['sub'],
            $row['progress'],
            $row['detail'],
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

    /**
     * Recursively flattens the objective tree (objective → key_results +
     * children) into flat rows — same flattening shape used by the PDF
     * Blade view (duplicated rather than shared, matching this module's
     * established precedent for small transverse logic).
     *
     * @param array<int, array<string, mixed>> $objectives
     * @return array<int, array{type: string, title: string, sub: string, progress: mixed, detail: string}>
     */
    private function flatten(array $objectives): array
    {
        $rows = [];

        foreach ($objectives as $objective) {
            $rows[] = [
                'type'     => 'Objectif',
                'title'    => $objective['title'] ?? '',
                'sub'      => $objective['level'] ?? ($objective['status'] ?? ''),
                'progress' => $objective['progress'] ?? '',
                'detail'   => $objective['status'] ?? '',
            ];

            foreach ($objective['key_results'] ?? [] as $kr) {
                $rows[] = [
                    'type'     => '— Résultat clé',
                    'title'    => $kr['title'] ?? '',
                    'sub'      => $kr['confidence'] ?? '',
                    'progress' => $kr['progress'] ?? '',
                    'detail'   => trim(($kr['current_value'] ?? '').' / '.($kr['target_value'] ?? '').' '.($kr['unit'] ?? '')),
                ];
            }

            if (! empty($objective['children'])) {
                $rows = array_merge($rows, $this->flatten($objective['children']));
            }
        }

        return $rows;
    }
}
