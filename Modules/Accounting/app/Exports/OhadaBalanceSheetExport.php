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
 * Chantier 29 — Excel export of the real SYSCOHADA Bilan
 * (`OhadaReportService::generateBalanceSheet()`'s output, the same data the
 * on-screen `BalanceSheet.vue` page renders). Flattens the nested
 * rubrique/account structure into one row per account line plus a per-section
 * total row, ACTIF then PASSIF.
 *
 * @implements WithMapping<array{side: string, section: string, code: string, label: string, montant: float}>
 */
class OhadaBalanceSheetExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /** @param array<string, mixed> $data OhadaReportService::generateBalanceSheet()'s return value. */
    public function __construct(private readonly array $data) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach (['ACTIF' => $this->data['actif'] ?? [], 'PASSIF' => $this->data['passif'] ?? []] as $side => $sections) {
            foreach ($sections as $section) {
                foreach (($section['accounts'] ?? []) as $account) {
                    $rows->push([
                        'side'    => $side,
                        'section' => $section['label_fr'] ?? '',
                        'code'    => (string) ($account['account_code'] ?? ''),
                        'label'   => $account['label_fr'] ?? '',
                        'montant' => (float) ($account['montant'] ?? 0),
                    ]);
                }

                $rows->push([
                    'side'    => $side,
                    'section' => ($section['label_fr'] ?? '').' — Total',
                    'code'    => '',
                    'label'   => '',
                    'montant' => (float) ($section['total'] ?? 0),
                ]);
            }
        }

        $rows->push([
            'side' => 'TOTAL', 'section' => 'Total actif', 'code' => '', 'label' => '',
            'montant' => (float) ($this->data['totaux']['total_actif'] ?? 0),
        ]);
        $rows->push([
            'side' => 'TOTAL', 'section' => 'Total passif', 'code' => '', 'label' => '',
            'montant' => (float) ($this->data['totaux']['total_passif'] ?? 0),
        ]);

        return $rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        $currency = $this->data['currency'] ?? 'MGA';

        return ['Colonne', 'Rubrique', 'Compte', 'Libellé', "Montant ({$currency})"];
    }

    public function map(mixed $row): array
    {
        return [
            $row['side'],
            $row['section'],
            $row['code'],
            $row['label'],
            number_format($row['montant'], 2),
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
