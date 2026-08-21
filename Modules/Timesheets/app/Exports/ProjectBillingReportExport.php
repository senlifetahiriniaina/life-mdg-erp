<?php

declare(strict_types=1);

namespace Modules\Timesheets\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Chantier 32.19 (Timesheets deep 14-layer audit, layer 14c — proposed
 * report, Chantier 29's own catalogue named this exact report for this
 * module: "rapport de facturation/heures par projet"). Two real tabs,
 * mirroring the JSON endpoint's own two aggregations exactly — no new
 * calculation invented, same pattern already established by
 * Modules\Strategy\Exports\StrategyExecutiveReportExport (Chantier 29).
 */
class ProjectBillingReportExport implements WithMultipleSheets
{
    /**
     * @param array{by_project: list<array<string, mixed>>, by_employee_project: list<array<string, mixed>>} $data
     *     Same shape as ProjectBillingService::getProjectBillingReportData().
     */
    public function __construct(private readonly array $data) {}

    /** @return array<int, \Maatwebsite\Excel\Concerns\FromCollection> */
    public function sheets(): array
    {
        return [
            new Sheets\ByProjectSheet($this->data['by_project'] ?? []),
            new Sheets\ByEmployeeProjectSheet($this->data['by_employee_project'] ?? []),
        ];
    }
}
