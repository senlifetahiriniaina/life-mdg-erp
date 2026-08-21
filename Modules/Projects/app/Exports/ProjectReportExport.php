<?php

declare(strict_types=1);

namespace Modules\Projects\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Chantier 32.17 (14-layer deep audit, layer 14 — Excel export): Projects
 * already had a real, working PDF export (Modules\Projects\Http\Controllers\
 * Api\ProjectReportController::pdf(), Chantier 8.4) but no Excel equivalent
 * — a documented gap since Chantier 29's cross-module export audit
 * ("Projects: already covered (PDF réel existant) — ajouter l'export Excel
 * manquant"). Built against the exact same real data
 * ProjectReportService::generateStatusReport() already computes for the PDF
 * — no new calculation invented, same `maatwebsite/excel` WithMultipleSheets
 * pattern already established by Strategy's executive-report export
 * (Chantier 29).
 */
class ProjectReportExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $data  Same shape as
     *   ProjectReportService::generateStatusReport().
     * @param  array<int, array<string, mixed>>  $taskRows  From
     *   ProjectReportService::taskRows() — a distinct, row-level shape.
     */
    public function __construct(
        private readonly array $data,
        private readonly array $taskRows,
    ) {}

    /** @return array<int, \Maatwebsite\Excel\Concerns\FromCollection> */
    public function sheets(): array
    {
        return [
            new Sheets\TasksSheet($this->taskRows),
            new Sheets\TeamHoursSheet($this->data['team_hours'] ?? []),
            new Sheets\MilestonesSheet($this->data['milestones'] ?? []),
        ];
    }
}
