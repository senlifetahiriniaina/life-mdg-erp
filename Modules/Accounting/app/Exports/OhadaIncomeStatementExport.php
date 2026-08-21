<?php

declare(strict_types=1);

namespace Modules\Accounting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Chantier 29 — Excel export of the real SYSCOHADA Compte de Résultat
 * (`OhadaReportService::generateIncomeStatement()`'s output, the same data
 * the on-screen `IncomeStatement.vue` page renders). One row per
 * produit/charge line plus the CA→marge→résultat cascade totals.
 *
 * @implements WithMapping<array{type: string, code: string, label: string, current: float, previous: ?float}>
 */
class OhadaIncomeStatementExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /** @param array<string, mixed> $data OhadaReportService::generateIncomeStatement()'s return value. */
    public function __construct(private readonly array $data) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach (($this->data['produits'] ?? []) as $row) {
            $rows->push([
                'type' => 'Produits', 'code' => $row['code'] ?? '', 'label' => $row['label_fr'] ?? '',
                'current' => (float) ($row['current'] ?? 0), 'previous' => (float) ($row['previous'] ?? 0),
            ]);
        }

        foreach (($this->data['charges'] ?? []) as $row) {
            $rows->push([
                'type' => 'Charges', 'code' => $row['code'] ?? '', 'label' => $row['label_fr'] ?? '',
                'current' => (float) ($row['current'] ?? 0), 'previous' => (float) ($row['previous'] ?? 0),
            ]);
        }

        $totaux = $this->data['totaux'] ?? [];
        foreach ([
            "Chiffre d'affaires"        => 'chiffre_affaires',
            'Marge brute'                => 'marge_brute',
            "Résultat d'exploitation"    => 'resultat_exploitation',
            'Résultat financier'         => 'resultat_financier',
            'Résultat net'               => 'resultat_net',
        ] as $label => $key) {
            $rows->push([
                'type' => 'Totaux', 'code' => '', 'label' => $label,
                'current' => (float) ($totaux[$key] ?? 0), 'previous' => (float) ($totaux[$key.'_n1'] ?? 0),
            ]);
        }

        return $rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Type', 'Compte', 'Libellé', 'Période', 'N-1'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['type'],
            $row['code'],
            $row['label'],
            number_format($row['current'], 2),
            $row['previous'] === null ? '' : number_format($row['previous'], 2),
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
