<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Exports;

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
 * Chantier 32.21: all-support-agents metrics table (one row per agent),
 * backing AgentPerformanceController::metricsExport() — a confirmed second
 * instance of the exact same fake-URL-never-generates-a-file bug already
 * fixed on reportExport() in the same class, found while reading it.
 *
 * @implements WithMapping<array<string, mixed>>
 */
class AgentMetricsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param Collection<int, array<string, mixed>> $agentMetrics each row already carries a real `agent_name` key alongside buildMetrics()'s fields */
    public function __construct(private readonly Collection $agentMetrics) {}

    public function collection(): Collection
    {
        return $this->agentMetrics;
    }

    public function title(): string
    {
        return 'Métriques agents';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Agent', 'ID', 'Tickets traités', 'Tickets résolus',
            'Temps de résolution moyen (h)', 'Satisfaction moyenne',
            'Conformité SLA (%)', 'Première réponse moyenne (min)',
        ];
    }

    /** @param array<string, mixed> $row */
    public function map($row): array
    {
        return [
            $row['agent_name'] ?? ('Agent #' . $row['agent_id']),
            $row['agent_id'],
            $row['total_tickets'],
            $row['resolved_tickets'],
            $row['average_resolution_time_hours'],
            $row['average_satisfaction_score'],
            round(($row['sla_compliance_rate'] ?? 0) * 100, 1),
            $row['first_response_time_minutes'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E5BE8');
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');

        return [];
    }
}
