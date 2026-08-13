<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\VatDeclaration;
use Modules\Accounting\Models\VatRate;

class VatDeclarationService
{
    /**
     * Calculate a VAT declaration for a given period.
     *
     * @param  string  $type  monthly|quarterly
     */
    public function calculatePeriod(int $year, int $period, string $type): VatDeclaration
    {
        [$startDate, $endDate] = $this->getPeriodDates($year, $period, $type);

        // Sales invoices (type = invoice)
        $salesQuery = Invoice::where('type', 'invoice')
            ->whereIn('status', ['sent', 'paid'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        $totalSales = (float) $salesQuery->sum('subtotal');
        $vatCollected = (float) $salesQuery->sum('tax_amount');

        // Purchase invoices (type = bill)
        $purchasesQuery = Invoice::where('type', 'bill')
            ->whereIn('status', ['sent', 'paid'])
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        $totalPurchases = (float) $purchasesQuery->sum('subtotal');
        $vatDeductible = (float) $purchasesQuery->sum('tax_amount');

        $vatDue = max(0.0, $vatCollected - $vatDeductible);

        /** @var VatDeclaration $declaration */
        $declaration = VatDeclaration::updateOrCreate(
            [
                'period_type' => $type,
                'period_year' => $year,
                'period_number' => $period,
            ],
            [
                'status' => 'draft',
                'total_sales' => $totalSales,
                'total_purchases' => $totalPurchases,
                'vat_collected' => $vatCollected,
                'vat_deductible' => $vatDeductible,
                'vat_due' => $vatDue,
            ]
        );

        return $declaration;
    }

    /**
     * Generate a detailed breakdown of the declaration by VAT rate.
     *
     * @return array<string, mixed>
     */
    public function generateDeclarationReport(VatDeclaration $decl): array
    {
        [$startDate, $endDate] = $this->getPeriodDates($decl->period_year, $decl->period_number, $decl->period_type);

        // OPTIMIZED: Single aggregated query instead of N+1 queries
        // Get all VAT rates with their aggregated sales and purchase data in one query
        $salesData = \DB::table('acc_invoice_lines as il')
            ->join('acc_invoices as i', 'i.id', '=', 'il.invoice_id')
            ->where('i.type', 'invoice')
            ->whereIn('i.status', ['sent', 'paid'])
            ->whereBetween('i.invoice_date', [$startDate, $endDate])
            ->groupBy('il.tax_rate')
            ->selectRaw('il.tax_rate, SUM(il.subtotal) as base, SUM(il.tax_amount) as vat')
            ->get()
            ->keyBy('tax_rate');

        $purchaseData = \DB::table('acc_invoice_lines as il')
            ->join('acc_invoices as i', 'i.id', '=', 'il.invoice_id')
            ->where('i.type', 'bill')
            ->whereIn('i.status', ['sent', 'paid'])
            ->whereBetween('i.invoice_date', [$startDate, $endDate])
            ->groupBy('il.tax_rate')
            ->selectRaw('il.tax_rate, SUM(il.subtotal) as base, SUM(il.tax_amount) as vat')
            ->get()
            ->keyBy('tax_rate');

        // Get all active VAT rates
        $rates = VatRate::where('is_active', true)->get();

        $breakdown = [];
        foreach ($rates as $rate) {
            $sales = $salesData->get($rate->rate);
            $purchase = $purchaseData->get($rate->rate);

            $breakdown[] = [
                'rate' => $rate->rate,
                'name' => $rate->name,
                'type' => $rate->type,
                'sales_base' => (float) ($sales?->base ?? 0),
                'sales_vat' => (float) ($sales?->vat ?? 0),
                'purchases_base' => (float) ($purchase?->base ?? 0),
                'purchases_vat' => (float) ($purchase?->vat ?? 0),
            ];
        }

        return [
            'declaration' => $decl->toArray(),
            'period' => ['start' => $startDate, 'end' => $endDate],
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Export the declaration as EU VAT XML.
     */
    public function exportDeclarationXml(VatDeclaration $decl): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><VatDeclaration/>');
        $xml->addAttribute('xmlns', 'urn:eu:vat:declaration:v1');

        $period = $xml->addChild('Period');
        $period->addChild('Type', $decl->period_type);
        $period->addChild('Year', (string) $decl->period_year);
        $period->addChild('Number', (string) $decl->period_number);

        $amounts = $xml->addChild('Amounts');
        $amounts->addChild('TotalSales', number_format((float) $decl->total_sales, 2, '.', ''));
        $amounts->addChild('TotalPurchases', number_format((float) $decl->total_purchases, 2, '.', ''));
        $amounts->addChild('VatCollected', number_format((float) $decl->vat_collected, 2, '.', ''));
        $amounts->addChild('VatDeductible', number_format((float) $decl->vat_deductible, 2, '.', ''));
        $amounts->addChild('VatDue', number_format((float) $decl->vat_due, 2, '.', ''));

        $status = $xml->addChild('Status', $decl->status);
        if ($decl->reference !== null) {
            $xml->addChild('Reference', $decl->reference);
        }

        $result = $xml->asXML();

        return $result !== false ? $result : '';
    }

    /**
     * Mark a declaration as submitted.
     */
    public function markSubmitted(VatDeclaration $decl, string $reference): void
    {
        $decl->update([
            'status' => 'submitted',
            'reference' => $reference,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Get start/end dates for a period.
     *
     * @return array{string, string}
     */
    private function getPeriodDates(int $year, int $period, string $type): array
    {
        if ($type === 'monthly') {
            $start = sprintf('%04d-%02d-01', $year, $period);
            $end = date('Y-m-t', strtotime($start)) . ' 23:59:59';
        } else {
            // quarterly: period 1=Q1, 2=Q2, 3=Q3, 4=Q4
            $startMonth = ($period - 1) * 3 + 1;
            $endMonth = $startMonth + 2;
            $start = sprintf('%04d-%02d-01', $year, $startMonth);
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $endMonth))) . ' 23:59:59';
        }

        return [$start, $end];
    }
}
