<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Accounting\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Payroll Integration Service
 * Bridges HR payroll processing with Accounting (OHADA-compliant, multi-country).
 */
class PayrollIntegrationService
{
    /**
     * Generate payroll records for all active employees in a period.
     */
    public function generatePayslips(
        int|null $tenantId,
        Carbon $startDate,
        Carbon $endDate,
        string $payrollCycle = 'monthly'
    ): array {
        // Chantier 8.3: hr_employees.tenant_id is a real column but not in
        // Employee's $fillable — never set by any real create()/update() call
        // in this app (confirmed: EmployeeController::store() and every other
        // live Employee write path skip it entirely), so filtering by it here
        // silently returned zero employees for any tenant. Employee has no
        // real tenant scoping today (EmployeeController::index(), the live
        // employee-listing endpoint, doesn't filter by tenant either) — drop
        // the filter to match how Employee is actually queried elsewhere.
        $employees = Employee::where('status', 'active')
            ->whereNull('termination_date')
            ->get();

        $records = [];
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $record = $this->generatePayslip($employee, $startDate, $endDate, $payrollCycle, $tenantId);
                if ($record) {
                    $records[] = $record->id;
                }
            } catch (\Exception $e) {
                $name = trim("{$employee->first_name} {$employee->last_name}");
                Log::error("Failed to generate payslip for {$name}", ['error' => $e->getMessage()]);
                $errors[] = [
                    'employee_id' => $employee->id,
                    'name'        => $name,
                    'error'       => $e->getMessage(),
                ];
            }
        }

        return [
            'created_count' => count($records),
            'error_count'   => count($errors),
            'payslip_ids'   => $records,
            'errors'        => $errors,
        ];
    }

    /**
     * Generate a payslip for a single employee (Payroll module's Payslip is
     * the single source of truth — the legacy HR PayrollRecord was removed).
     */
    public function generatePayslip(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
        string $payrollCycle = 'monthly',
        ?int $tenantId = null
    ): ?Payslip {
        // Idempotent — skip if already exists for this period
        $existing = Payslip::where('employee_id', $employee->id)
            ->whereDate('period', $startDate->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        // Chantier 8.3: hr_employees.tenant_id/salary_currency are real
        // columns but not in Employee's $fillable — never populated by any
        // real create()/update() call, so this always resolved to 0/'XOF'
        // regardless of the actual employee. tenant_id now comes from the
        // caller (generatePayslips() already has the real value from the
        // authenticated user); when called standalone (as this method's own
        // test does) it falls back to the employee's linked User's real,
        // live tenant_id column. Currency comes from the employee's current
        // EmployeeCompensation record (the real salary source — see below).
        $tenantId ??= (int) ($employee->user?->tenant_id ?? 0);
        $compensation = $this->getCurrentCompensation($employee, $startDate);
        $currency = $compensation?->currency ?? 'XOF';

        $periodDate = $startDate->copy()->startOfMonth()->toDateString();
        $run = PayrollRun::where('tenant_id', $tenantId)
            ->whereDate('period', $periodDate)
            ->first()
            ?? PayrollRun::create([
                'tenant_id' => $tenantId,
                'period'    => $periodDate,
                'status'    => 'draft',
                'currency'  => $currency,
            ]);

        $components = $this->calculateSalaryComponents($employee, $startDate, $endDate, $compensation);
        $grossSalary = $components['gross_salary'];
        $deductions  = $this->calculateDeductions($employee, $grossSalary);
        $netSalary   = $grossSalary - $deductions['total_deductions'];

        return Payslip::create([
            'payroll_run_id'    => $run->id,
            'tenant_id'         => $tenantId,
            'employee_id'       => $employee->id,
            'employee_name'     => trim("{$employee->first_name} {$employee->last_name}"),
            'period'            => $startDate->toDateString(),
            'salary_components' => array_merge($components, ['deductions' => $deductions]),
            'gross_salary'      => $grossSalary,
            'total_deductions'  => $deductions['total_deductions'],
            'net_salary'        => $netSalary,
            'currency'          => $currency,
            'status'            => 'draft',
        ]);
    }

    /**
     * Calculate salary components (base, allowances, overtime, bonuses).
     */
    public function calculateSalaryComponents(
        Employee $employee,
        Carbon $startDate,
        Carbon $endDate,
        ?EmployeeCompensation $compensation = null
    ): array {
        // Chantier 8.3 (headline finding): base_salary/housing_allowance/
        // transport_allowance/family_allowance/monthly_bonus were all read
        // straight off Employee, but none of these are real Employee
        // columns/fillable fields — nothing in the real HR onboarding flow
        // (EmployeeController::store(), EmployeeManagementService) ever
        // writes them, so every real payslip silently computed to a near-zero
        // salary. The real salary source is EmployeeCompensation (see
        // CompensationService) — base_salary comes from there now. The
        // granular allowance/bonus sub-categories still have no real
        // per-employee data source (only a single aggregate bonus_amount/
        // benefits_annual_value exists on EmployeeCompensation) — left at 0
        // rather than guessing a split, same fallback-first pattern already
        // used elsewhere in this app (e.g. Strategy's training_roi ratio).
        $compensation ??= $this->getCurrentCompensation($employee, $startDate);
        $baseSalary = (float) ($compensation?->base_salary ?? 0);

        $allowances = [
            'housing_allowance'     => (float) ($employee->housing_allowance ?? 0),
            'transport_allowance'   => (float) ($employee->transport_allowance ?? 0),
            'family_allowance'      => (float) ($employee->family_allowance ?? 0),
            'performance_allowance' => $this->calculatePerformanceAllowance($employee, $baseSalary),
        ];
        $totalAllowances = array_sum($allowances);

        $overtime     = $this->calculateOvertime($employee, $startDate, $endDate, $baseSalary);
        $bonuses      = [
            'monthly_bonus'     => (float) ($employee->monthly_bonus ?? 0),
            'performance_bonus' => 0.0,
        ];
        $totalBonuses = array_sum($bonuses);
        $grossSalary  = $baseSalary + $totalAllowances + $overtime['total'] + $totalBonuses;

        return [
            'base_salary'      => $baseSalary,
            'allowances'       => $allowances,
            'total_allowances' => $totalAllowances,
            'overtime'         => $overtime,
            'bonuses'          => $bonuses,
            'total_bonuses'    => $totalBonuses,
            'gross_salary'     => $grossSalary,
        ];
    }

    /**
     * Calculate all deductions (taxes, social security, health, pension, loans).
     */
    public function calculateDeductions(Employee $employee, float $grossSalary): array
    {
        $countryCode = $employee->country_code ?? $employee->nationality ?? 'SN';

        $deductions = [
            'income_tax'         => $this->calculateIncomeTax($grossSalary, $countryCode),
            'social_security'    => $this->calculateSocialSecurity($grossSalary, $countryCode),
            'health_insurance'   => (float) ($employee->health_insurance_contribution ?? 0),
            'pension_contribution'=> $this->calculatePensionContribution($grossSalary, $countryCode),
            'loan_repayment'     => $this->calculateLoanRepayment($employee),
            'union_dues'         => (float) ($employee->union_dues ?? 0),
        ];

        return [
            'deductions'       => $deductions,
            'total_deductions' => array_sum($deductions),
        ];
    }

    /**
     * Calculate income tax by country (OHADA-compliant).
     */
    public function calculateIncomeTax(float $grossSalary, string $countryCode): float
    {
        return match ($countryCode) {
            'SN' => $this->calculateSenegalTax($grossSalary),
            'CI' => $this->calculateIvoryCoastTax($grossSalary),
            'CM' => $this->calculateCameroonTax($grossSalary),
            'NG' => $this->calculateNigeriaTax($grossSalary),
            default => $this->calculateOhadaTax($grossSalary),
        };
    }

    private function calculateSenegalTax(float $salary): float
    {
        $threshold = 400_000;
        return $salary <= $threshold ? 0.0 : ($salary - $threshold) * 0.20;
    }

    private function calculateIvoryCoastTax(float $salary): float
    {
        return $salary * 0.18;
    }

    private function calculateCameroonTax(float $salary): float
    {
        if ($salary <= 250_000) return 0.0;
        if ($salary <= 500_000) return ($salary - 250_000) * 0.10;
        if ($salary <= 1_000_000) return 25_000 + (($salary - 500_000) * 0.15);
        return 100_000 + (($salary - 1_000_000) * 0.20);
    }

    private function calculateNigeriaTax(float $salary): float
    {
        if ($salary <= 100_000) return 0.0;
        if ($salary <= 300_000) return ($salary - 100_000) * 0.05;
        return 10_000 + (($salary - 300_000) * 0.10);
    }

    private function calculateOhadaTax(float $salary): float
    {
        return $salary * 0.15;
    }

    public function calculateSocialSecurity(float $grossSalary, string $countryCode): float
    {
        return $grossSalary * match ($countryCode) {
            'SN'    => 0.055,
            'CI'    => 0.065,
            'CM'    => 0.058,
            'NG'    => 0.08,
            default => 0.06,
        };
    }

    public function calculatePensionContribution(float $grossSalary, string $countryCode): float
    {
        return $grossSalary * match ($countryCode) {
            'SN'    => 0.05,
            'CI'    => 0.055,
            'CM'    => 0.04,
            'NG'    => 0.045,
            default => 0.05,
        };
    }

    private function calculatePerformanceAllowance(Employee $employee, float $baseSalary): float
    {
        // Chantier 8.3: was re-reading the phantom Employee::base_salary
        // field (same bug as calculateSalaryComponents()'s headline fix) —
        // now takes the already-resolved real base salary from the caller.
        $rating = $employee->latest_performance_rating ?? 3;

        return $baseSalary * match ($rating) {
            5       => 0.15,
            4       => 0.10,
            3       => 0.05,
            2       => 0.02,
            default => 0.0,
        };
    }

    private function calculateOvertime(Employee $employee, Carbon $startDate, Carbon $endDate, float $baseSalary): array
    {
        $overtimeHours = DB::table('hr_timesheets')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('overtime_hours');

        // Chantier 8.3: was re-reading the phantom Employee::base_salary
        // field — now takes the already-resolved real base salary.
        $hourlyRate = $baseSalary / 160;

        return [
            'hours'       => (float) $overtimeHours,
            'hourly_rate' => $hourlyRate,
            'multiplier'  => 1.5,
            'total'       => (float) $overtimeHours * $hourlyRate * 1.5,
        ];
    }

    /**
     * The employee's compensation record effective as of a given date —
     * the real, single source of truth for salary data (see
     * Modules\HR\Services\CompensationService, which uses the same query
     * shape against now() rather than an arbitrary period date).
     */
    private function getCurrentCompensation(Employee $employee, Carbon $asOf): ?EmployeeCompensation
    {
        return $employee->compensations()
            ->where('effective_date', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $asOf->toDateString());
            })
            ->latest('effective_date')
            ->first();
    }

    private function calculateLoanRepayment(Employee $employee): float
    {
        return (float) DB::table('hr_employee_loans')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->sum('monthly_installment');
    }

    /**
     * Post approved payslips as OHADA journal entries.
     */
    public function postPayslipsToAccounting(array $payslipIds): array
    {
        $records = Payslip::whereIn('id', $payslipIds)
            ->where('status', 'approved')
            ->get();

        $posted = [];

        foreach ($records as $record) {
            $name = $record->employee_name;
            $ref  = "PAYROLL-{$record->id}";

            // Debit: Salary expense (OHADA Cl.6161)
            JournalEntry::create([
                'entry_date'     => now(),
                'entry_type'     => 'debit',
                'amount'         => $record->gross_salary,
                'reference_type' => 'Payslip',
                'reference_id'   => $record->id,
                'description'    => "Salaire brut — {$name}",
                'status'         => 'posted',
                'notes'          => $ref,
            ]);

            // Credit: Salary payable (OHADA Cl.4210)
            JournalEntry::create([
                'entry_date'     => now(),
                'entry_type'     => 'credit',
                'amount'         => $record->net_salary,
                'reference_type' => 'Payslip',
                'reference_id'   => $record->id,
                'description'    => "Salaire net à payer — {$name}",
                'status'         => 'posted',
                'notes'          => $ref,
            ]);

            $posted[] = $record->id;
        }

        return [
            'posted_count' => count($posted),
            'payslip_ids'  => $posted,
        ];
    }

    /**
     * Payroll summary for a given period and tenant.
     */
    public function getPayrollSummary(int|null $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $records = Payslip::where('tenant_id', (int) ($tenantId ?? 0))
            ->whereDate('period', $startDate->toDateString())
            ->get();

        $totalGross      = $records->sum('gross_salary');
        $totalNet        = $records->sum('net_salary');
        $totalDeductions = $totalGross - $totalNet;

        return [
            'employee_count'    => $records->count(),
            'total_gross'       => $totalGross,
            'total_deductions'  => $totalDeductions,
            'total_net'         => $totalNet,
            'average_salary'    => $records->count() > 0 ? $totalGross / $records->count() : 0,
            'payslips_draft'    => $records->where('status', 'draft')->count(),
            'payslips_approved' => $records->where('status', 'approved')->count(),
            'payslips_paid'     => $records->where('status', 'paid')->count(),
        ];
    }
}
