<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Chantier 32.21: single-agent performance report (the same data
 * AgentPerformanceController::buildReportData() feeds the PDF export and
 * the JSON report() endpoint with) — one row per metric, flat key/value.
 */
class AgentPerformanceReportExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /** @param array{metrics: array<string, mixed>, summary: array<string, mixed>, highlights: array<int, string>, concerns: array<int, string>} $data */
    public function __construct(
        private readonly int $agentId,
        private readonly string $agentName,
        private readonly array $data,
    ) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->data['metrics'] as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $rows->push([
                'metric' => ucwords(str_replace('_', ' ', (string) $key)),
                'value' => $value,
            ]);
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Performance agent';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ["Agent : {$this->agentName} (#{$this->agentId})", 'Valeur'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2E5BE8');
        $sheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');

        return [];
    }
}
