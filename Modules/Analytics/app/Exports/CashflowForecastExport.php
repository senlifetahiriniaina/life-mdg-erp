<?php

declare(strict_types=1);

namespace Modules\Analytics\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export Excel de la prévision de trésorerie (Chantier 26, volet A) — une
 * ligne par jour projeté (date/encaissement/décaissement/net/solde cumulé).
 *
 * @implements WithMapping<array{date: string, inflow: float, outflow: float, net: float, running_balance: float}>
 */
class CashflowForecastExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly array $daily,
        private readonly string $currency,
    ) {}

    public function collection(): Collection
    {
        return collect($this->daily);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Date', "Encaissement ({$this->currency})", "Décaissement ({$this->currency})", "Net ({$this->currency})", "Solde cumulé ({$this->currency})"];
    }

    public function map(mixed $row): array
    {
        return [
            $row['date'],
            number_format((float) $row['inflow'], 2),
            number_format((float) $row['outflow'], 2),
            number_format((float) $row['net'], 2),
            number_format((float) $row['running_balance'], 2),
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
