<?php

declare(strict_types=1);

namespace Modules\Projects\Exports\Sheets;

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
 * Onglet "Tâches" — une ligne par tâche du projet, depuis
 * ProjectReportService::taskRows().
 *
 * @implements WithMapping<array<string, mixed>>
 */
class TasksSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string
    {
        return 'Tâches';
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Titre', 'Statut', 'Priorité', 'Assigné à', 'Échéance', 'Heures estimées', 'Heures loguées'];
    }

    public function map(mixed $row): array
    {
        return [
            $row['title'] ?? '',
            $row['status'] ?? '',
            $row['priority'] ?? '',
            $row['assignee'] ?? '—',
            $row['due_date'] ?? '—',
            $row['estimated_hours'] ?? 0,
            $row['logged_hours'] ?? 0,
        ];
    }

    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2E5BE8');
        $sheet->getStyle('A1:G1')->getFont()->getColor()->setARGB('FFFFFFFF');
    }
}
