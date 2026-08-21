<?php

declare(strict_types=1);

namespace Modules\Strategy\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Chantier 29 — Rapport de pilotage stratégique, export Excel multi-onglets.
 *
 * `maatwebsite/excel` ^3.1 (confirmed installed, vendor/maatwebsite/excel
 * ships Concerns/WithMultipleSheets.php) supports a genuine multi-sheet
 * workbook via WithMultipleSheets — one real sheet per report section
 * (Ratios, Plans, Corrélations, OKR, KPI Sectoriels), each its own small
 * class implementing FromCollection/WithHeadings/WithTitle. No single flat
 * sheet fallback was needed.
 */
class StrategyExecutiveReportExport implements WithMultipleSheets
{
    /**
     * @param array<string, mixed> $data Same shape gathered by
     *     StrategyReportExportController::gatherReportData().
     */
    public function __construct(private readonly array $data) {}

    /** @return array<int, \Maatwebsite\Excel\Concerns\FromCollection> */
    public function sheets(): array
    {
        return [
            new Sheets\RatiosSheet($this->data['ratios'] ?? []),
            new Sheets\PlansSheet($this->data['plans'] ?? collect()),
            new Sheets\CorrelationsSheet($this->data['correlations'] ?? []),
            new Sheets\OkrSheet($this->data['okr'] ?? []),
            new Sheets\SectorKpiSheet($this->data['sector'] ?? []),
        ];
    }
}
