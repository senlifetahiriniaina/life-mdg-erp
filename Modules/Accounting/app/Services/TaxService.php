<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\TaxEntry;
use Modules\Accounting\Models\TaxRate;

class TaxService
{
    public function createTaxRate(array $data): TaxRate
    {
        return TaxRate::create($data);
    }

    public function updateTaxRate(TaxRate $rate, array $data): TaxRate
    {
        $rate->update($data);

        return $rate->fresh();
    }

    public function deleteTaxRate(TaxRate $rate): void
    {
        $rate->delete();
    }

    public function calculateTax(float $amount, float|TaxRate $rate): float
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if ($rate instanceof TaxRate) {
            $rateValue = (float) $rate->rate;
            if ($rateValue < 0 || $rateValue > 100) {
                throw new \InvalidArgumentException('Tax rate must be between 0 and 100');
            }
            return $rate->calculateTax($amount);
        }

        // float rates are treated as decimal multipliers (0.0–1.0)
        if ($rate < 0 || $rate > 1.0) {
            throw new \InvalidArgumentException('Tax rate must be between 0 and 1');
        }

        return round($amount * $rate, 2);
    }

    /**
     * Apply each rate in sequence (compound), returns breakdown and total.
     *
     * @param  (TaxRate|float)[]  $rates
     * @return float Total amount including base + all compound taxes
     */
    /**
     * @param  (TaxRate|float)[]  $rates
     * @return array{breakdown: list<array{rate_id?: int, rate_name?: string, tax_amount: float}>, total_tax: float}
     */
    public function calculateCompoundTax(float $amount, array $rates): array
    {
        $breakdown = [];
        $totalTax = 0.0;
        $running = $amount;

        foreach ($rates as $rate) {
            if ($rate instanceof TaxRate) {
                $taxAmount = $rate->calculateTax($running);
                $breakdown[] = [
                    'rate_id' => $rate->id,
                    'rate_name' => $rate->name,
                    'tax_amount' => $taxAmount,
                ];
            } else {
                $taxAmount = $this->calculateTax($running, $rate);
                $breakdown[] = [
                    'tax_amount' => $taxAmount,
                ];
            }
            $totalTax += $taxAmount;
            $running += $taxAmount;
        }

        return [
            'breakdown' => $breakdown,
            'total_tax' => $totalTax,
        ];
    }

    public function recordTaxEntry(array $data): TaxEntry
    {
        return TaxEntry::create($data);
    }

    /**
     * @return array{collected: float, paid: float, net_liability: float}
     */
    public function getTaxLiability(string $periodStart, string $periodEnd): array
    {
        $entries = TaxEntry::whereDate('period_start', '>=', $periodStart)
            ->whereDate('period_end', '<=', $periodEnd)
            ->get();

        $collected = (float) $entries->where('type', 'collected')->sum('tax_amount');
        $paid = (float) $entries->where('type', 'paid')->sum('tax_amount');

        return [
            'collected' => $collected,
            'paid' => $paid,
            'net_liability' => $collected - $paid,
        ];
    }

    /**
     * @return array{period_start: string, period_end: string, entries_count: int, total_collected: float, total_paid: float, net_liability: float, by_rate: list<array>}
     */
    public function getTaxReport(string $periodStart, string $periodEnd): array
    {
        $entries = TaxEntry::with('taxRate')
            ->whereDate('period_start', '>=', $periodStart)
            ->whereDate('period_end', '<=', $periodEnd)
            ->get();

        $totalCollected = (float) $entries->where('type', 'collected')->sum('tax_amount');
        $totalPaid = (float) $entries->where('type', 'paid')->sum('tax_amount');

        $byRate = $entries->groupBy('tax_rate_id')->map(function (Collection $group) {
            $first = $group->first();
            $collected = (float) $group->where('type', 'collected')->sum('tax_amount');
            $paid = (float) $group->where('type', 'paid')->sum('tax_amount');

            return [
                'tax_rate_id' => $first->tax_rate_id,
                'tax_rate_name' => $first->taxRate?->name ?? '',
                'collected' => $collected,
                'paid' => $paid,
                'net' => $collected - $paid,
            ];
        })->values()->all();

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'entries_count' => $entries->count(),
            'total_collected' => $totalCollected,
            'total_paid' => $totalPaid,
            'net_liability' => $totalCollected - $totalPaid,
            'by_rate' => $byRate,
        ];
    }

    public function getActiveTaxRates(): Collection
    {
        return TaxRate::where('is_active', true)->get();
    }

    public function calculateTaxLiability(float $collected, float $deductible): float
    {
        return max(0.0, $collected - $deductible);
    }
}
