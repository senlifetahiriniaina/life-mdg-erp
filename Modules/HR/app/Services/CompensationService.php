<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;

/**
 * Service for managing employee compensation including total comp calculation,
 * equity vesting, and bonus accrual.
 *
 * Chantier 8.3: generateOfferLetterData()/getBenefitsDetails() were dropped —
 * a recruitment artefact issued before someone is an employee, the same
 * "offer-letter generation" exclusion already documented in CLAUDE.md for
 * HR's "basique" scope (ATS/recruitment out of scope).
 */
class CompensationService
{
    /**
     * Get current active compensation for an employee.
     */
    public function getCurrentCompensation(Employee $employee): ?EmployeeCompensation
    {
        return $employee->compensations()
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->latest('effective_date')
            ->first();
    }

    /**
     * Calculate total compensation for an employee.
     * Includes base salary, bonus, benefits, and equity.
     */
    public function calculateTotalCompensation(Employee $employee): float
    {
        $compensation = $this->getCurrentCompensation($employee);

        if (!$compensation) {
            return 0.0;
        }

        return $compensation->calculateTotalCompensation();
    }

    /**
     * Get compensation breakdown for an employee.
     */
    public function getCompensationBreakdown(Employee $employee): array
    {
        $compensation = $this->getCurrentCompensation($employee);

        if (!$compensation) {
            return [
                'base_salary' => 0.0,
                'bonus' => 0.0,
                'benefits' => 0.0,
                'equity' => 0.0,
                'total' => 0.0,
            ];
        }

        return [
            'base_salary' => (float) ($compensation->base_salary ?? 0.0),
            'bonus' => (float) ($compensation->bonus_amount ?? 0.0),
            'benefits' => (float) ($compensation->benefits_annual_value ?? 0.0),
            'equity' => (float) ($compensation->equity_granted ?? 0.0),
            'total' => $compensation->calculateTotalCompensation(),
        ];
    }

    /**
     * Create new compensation record for employee.
     */
    public function createCompensation(int $employeeId, array $data): EmployeeCompensation
    {
        // Close any active compensation records
        EmployeeCompensation::where('employee_id', $employeeId)
            ->where('effective_date', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->update(['end_date' => now()->subDay()->toDateString()]);

        return EmployeeCompensation::create(array_merge($data, [
            'employee_id' => $employeeId,
            'total_compensation' => $this->calculateTotalComp($data),
        ]));
    }

    /**
     * Update vesting schedule based on tenure.
     */
    public function updateEquityVesting(EmployeeCompensation $compensation): void
    {
        if (!$compensation->equity_granted || !$compensation->employee_id) {
            return;
        }

        $employee = Employee::find($compensation->employee_id);
        if (!$employee || !$employee->hire_date) {
            return;
        }

        $vestingPeriod = $compensation->equity_vesting_period_months ?? 48; // 4 years default
        $monthsEmployed = $employee->hire_date->diffInMonths(now());

        // Calculate vested percentage (linear vesting)
        $vestedPercentage = min(100, ($monthsEmployed / $vestingPeriod) * 100);

        $compensation->update([
            'equity_vested_percentage' => $vestedPercentage,
        ]);
    }

    /**
     * Calculate bonus accrual for a given period.
     */
    public function calculateBonusAccrual(Employee $employee, string $period = 'month'): float
    {
        $compensation = $this->getCurrentCompensation($employee);

        if (!$compensation || !$compensation->bonus_amount) {
            return 0.0;
        }

        return match ($compensation->bonus_frequency ?? 'annual') {
            'annual', 'semi-annual', 'quarterly' => ($period === 'month') ? $compensation->bonus_amount / 12 : $compensation->bonus_amount,
            default => 0.0,
        };
    }

    /**
     * Compare compensation to market benchmark.
     */
    public function compareToMarketBenchmark(Employee $employee, float $marketMedian): array
    {
        $totalComp = $this->calculateTotalCompensation($employee);
        $difference = $totalComp - $marketMedian;
        $percentDifference = ($marketMedian > 0) ? ($difference / $marketMedian) * 100 : 0;

        return [
            'employee_compensation' => $totalComp,
            'market_median' => $marketMedian,
            'difference' => $difference,
            'percent_difference' => round($percentDifference, 2),
            'below_market' => $totalComp < $marketMedian,
            'gap_amount' => $totalComp < $marketMedian ? abs($difference) : 0,
        ];
    }

    /**
     * Get compensation history for an employee.
     */
    public function getCompensationHistory(Employee $employee): Collection
    {
        return $employee->compensations()
            ->orderBy('effective_date', 'desc')
            ->get();
    }

    /**
     * Helper to calculate total comp from array data.
     */
    private function calculateTotalComp(array $data): float
    {
        return ($data['base_salary'] ?? 0) +
               ($data['bonus_amount'] ?? 0) +
               ($data['benefits_annual_value'] ?? 0) +
               ($data['equity_granted'] ?? 0);
    }

    /**
     * Audit all employee compensation records.
     */
    public function auditCompensationRecords(): array
    {
        $issues = [];

        // Check for employees without compensation
        $withoutComp = Employee::whereDoesntHave('compensations')
            ->where('status', 'active')
            ->count();

        if ($withoutComp > 0) {
            $issues[] = [
                'type' => 'missing_compensation',
                'count' => $withoutComp,
                'message' => "Found $withoutComp active employees without compensation records",
            ];
        }

        // Check for overlapping compensation records
        $today = now()->toDateString();

        $overlapping = DB::table('hr_employee_compensation')
            ->select('employee_id')
            ->selectRaw('COUNT(*) as count')
            ->where('effective_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->groupBy('employee_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if (count($overlapping) > 0) {
            $issues[] = [
                'type' => 'overlapping_records',
                'count' => count($overlapping),
                'message' => 'Found employees with overlapping active compensation records',
            ];
        }

        return $issues;
    }
}
