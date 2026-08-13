<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\ExpenseLine;
use Modules\Accounting\Models\ExpenseReport;
use RuntimeException;

class ExpenseService
{
    public function submit(ExpenseReport $report): void
    {
        if ($report->status !== 'draft') {
            throw new RuntimeException("Expense report #{$report->id} cannot be submitted (status: {$report->status}).");
        }

        $report->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approve(ExpenseReport $report): void
    {
        if ($report->status !== 'submitted') {
            throw new RuntimeException("Expense report #{$report->id} cannot be approved (status: {$report->status}).");
        }

        $report->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Add a mileage line to an expense report.
     */
    public function addMileage(ExpenseReport $report, float $km, float $rate): ExpenseLine
    {
        $amount = round($km * $rate, 2);

        /** @var ExpenseLine $line */
        $line = $report->lines()->create([
            'date' => now()->toDateString(),
            'category' => 'Transport',
            'description' => "Kilométrage : {$km} km × {$rate} €/km",
            'amount' => $amount,
            'currency' => 'EUR',
            'km' => $km,
        ]);

        // Recompute total
        $total = $report->lines()->sum('amount') + $amount;
        $report->update(['total' => $total]);

        return $line;
    }
}
