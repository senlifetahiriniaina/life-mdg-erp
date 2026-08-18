<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayrollRun;
use Modules\Accounting\Models\JournalEntry;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Payroll\Data\StatutorySchemes;
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
        // test does) it falls back to the employee's linked User's real
        // tenant boundary column. Currency comes from the employee's current
        // EmployeeCompensation record (the real salary source — see below).
        //
        // Chantier 10 correction: the fallback used to read the employee's
        // linked User's tenant_id — the same phantom column (real, migrated,
        // never in User::$fillable, never populated by the real registration
        // flow) already fixed repeatedly elsewhere in this session. Switched
        // to company_id, the real tenant boundary column.
        $tenantId ??= (int) ($employee->user?->company_id ?? 0);
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
        $deductions  = $this->calculateDeductions($employee, $grossSalary, $startDate, $endDate);
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
    public function calculateDeductions(Employee $employee, float $grossSalary, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $countryCode = $employee->country_code ?? $employee->nationality ?? 'SN';

        $deductions = [
            'income_tax'             => $this->calculateIncomeTax($grossSalary, $countryCode),
            'social_security'        => $this->calculateSocialSecurity($grossSalary, $countryCode),
            'health_insurance'       => (float) ($employee->health_insurance_contribution ?? 0),
            'pension_contribution'   => $this->calculatePensionContribution($grossSalary, $countryCode),
            'loan_repayment'         => $this->calculateLoanRepayment($employee),
            'union_dues'             => (float) ($employee->union_dues ?? 0),
            'unpaid_leave_deduction' => $this->calculateLeaveDeduction($employee, $grossSalary, $startDate, $endDate),
        ];

        return [
            'deductions'       => $deductions,
            'total_deductions' => array_sum($deductions),
        ];
    }

    /**
     * Chantier 10: unpaid/sick leave was never deducted from any real
     * payslip — calculateSalaryComponents()/calculateDeductions() had no
     * leave concept at all. Modules\Workflow's HrPayrollActionHandler::
     * adjustForLeave() computed a *preview* of what should be deducted every
     * time a leave request was approved, but only ever logged it (its own
     * "TODO: persist to payroll_adjustments table" — a table that never
     * existed). The real source of truth already exists and is already
     * persisted: the employee's own approved Modules\HR\Models\LeaveRequest
     * records — no new table needed, just wiring the existing one in at
     * generation time. Sick leave beyond the same 3-day grace period the
     * workflow handler already applies is treated as unpaid too, matching
     * that handler's own business rule rather than inventing a second one.
     */
    private function calculateLeaveDeduction(Employee $employee, float $grossSalary, ?Carbon $startDate, ?Carbon $endDate, int $sickGraceDays = 3): float
    {
        if (! $startDate || ! $endDate) {
            return 0.0;
        }

        $dailyRate = $grossSalary / 30;

        $requests = $employee->leaveRequests()
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('leaveType')
            ->get();

        $deductibleDays = 0.0;

        foreach ($requests as $request) {
            $days = (float) ($request->days ?? $request->days_requested ?? 0);
            $isPaid = $request->leaveType?->is_paid ?? true;
            $isSick = str_contains(mb_strtolower((string) ($request->leave_type ?? $request->type ?? $request->leaveType?->code ?? '')), 'sick')
                || str_contains(mb_strtolower((string) ($request->leaveType?->name ?? '')), 'malad');

            if ($isSick) {
                $deductibleDays += max(0.0, $days - $sickGraceDays);
            } elseif (! $isPaid) {
                $deductibleDays += $days;
            }
        }

        return round($dailyRate * $deductibleDays, 2);
    }

    /**
     * Calculate income tax by country (OHADA-compliant).
     *
     * Chantier 8.3: `StatutorySchemes` (8 real African statutory schedules —
     * SN/CI/CM/MG/BJ/TG/BF/ML, with real progressive brackets/abatements/
     * ceilings from the actual tax codes) existed fully written but had zero
     * consumers anywhere in this app — every calculation below was a crude
     * flat-rate/single-threshold approximation instead. Countries the real
     * dataset doesn't cover (NG, or any other code) keep the old
     * approximations as an explicit fallback rather than guessing new rates.
     */
    public function calculateIncomeTax(float $grossSalary, string $countryCode): float
    {
        $scheme = StatutorySchemes::country($countryCode);
        if ($scheme !== null) {
            return $this->calculateProgressiveIncomeTax($grossSalary, $scheme['income_tax']);
        }

        return match ($countryCode) {
            'NG'    => $this->calculateNigeriaTax($grossSalary),
            default => $this->calculateOhadaTax($grossSalary),
        };
    }

    /**
     * Progressive monthly withholding against a StatutorySchemes country's
     * `income_tax` definition: professional-expense abatement (rate, capped
     * where the country defines a cap) reduces the taxable base, then
     * marginal brackets apply, then any flat surtax/surcharge/minimum.
     */
    private function calculateProgressiveIncomeTax(float $grossSalary, array $incomeTax): float
    {
        $deduction = $grossSalary * (float) ($incomeTax['abatement_rate'] ?? 0.0);
        if (($incomeTax['abatement_cap'] ?? null) !== null) {
            $deduction = min($deduction, (float) $incomeTax['abatement_cap']);
        }
        $taxableBase = max(0.0, $grossSalary - $deduction);

        $tax = 0.0;
        foreach ($incomeTax['brackets'] as [$lower, $upper, $rate]) {
            if ($taxableBase <= $lower) {
                break;
            }
            $bandTop = $upper === null ? $taxableBase : min($taxableBase, $upper);
            $tax += max(0.0, $bandTop - $lower) * $rate;
        }

        $tax *= 1 + (float) ($incomeTax['surcharge_rate'] ?? 0.0);
        $tax += (float) ($incomeTax['fixed_tax'] ?? 0);

        if (($incomeTax['minimum_tax'] ?? null) !== null) {
            $tax = max($tax, (float) $incomeTax['minimum_tax']);
        }

        return round($tax, 2);
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

    /**
     * Employee-side social contributions (family benefits/health/work-
     * accident schemes — everything StatutorySchemes categorizes as
     * anything other than 'pension'). Countries with a single combined
     * scheme (BJ/TG/BF/ML — no legal split into pension vs. the rest) are
     * folded entirely into this bucket, matching the old code's own
     * behavior of treating social_security as the general contribution
     * figure; calculatePensionContribution() returns 0 for them rather
     * than guessing a split this dataset doesn't provide.
     */
    public function calculateSocialSecurity(float $grossSalary, string $countryCode): float
    {
        $scheme = StatutorySchemes::country($countryCode);
        if ($scheme !== null) {
            return $this->sumSchemeContributions($scheme['schemes'], $grossSalary, ['social_security', 'health', 'combined']);
        }

        return $grossSalary * match ($countryCode) {
            'NG'    => 0.08,
            default => 0.06,
        };
    }

    public function calculatePensionContribution(float $grossSalary, string $countryCode): float
    {
        $scheme = StatutorySchemes::country($countryCode);
        if ($scheme !== null) {
            return $this->sumSchemeContributions($scheme['schemes'], $grossSalary, ['pension']);
        }

        return $grossSalary * match ($countryCode) {
            'NG'    => 0.045,
            default => 0.05,
        };
    }

    /**
     * Sum employee-side contributions across the schemes matching the
     * given categories, respecting each scheme's own monthly ceiling.
     *
     * @param array<string, array<string, mixed>> $schemes
     * @param string[] $categories
     */
    private function sumSchemeContributions(array $schemes, float $grossSalary, array $categories): float
    {
        $total = 0.0;
        foreach ($schemes as $scheme) {
            if (!in_array($scheme['category'], $categories, true)) {
                continue;
            }
            $base = $scheme['ceiling'] !== null ? min($grossSalary, (float) $scheme['ceiling']) : $grossSalary;
            $total += $base * (float) $scheme['employee_rate'];
        }

        return round($total, 2);
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
        // Chantier 8.3: was reading `hr_timesheets.overtime_hours` — a dead
        // stub table (created by an early scaffold migration) with zero
        // writers anywhere in this app, so this always returned 0. The
        // real, live time-tracking data is Modules\Timesheets\TimesheetEntry
        // (`timesheet_entries`, hours_worked/entry_date/status), but that
        // model has no distinct "overtime" flag of its own — nothing in
        // this app tracks per-entry overtime as a separate concept. Derive
        // it the same way the standard-hourly-rate divisor below (160h a
        // month, ~40h/week) already implies: hours actually worked beyond
        // that standard in the period, on approved entries only.
        $hoursWorked = (float) TimesheetEntry::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereBetween('entry_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('hours_worked');

        $overtimeHours = max(0.0, $hoursWorked - 160.0);

        // Chantier 8.3: was re-reading the phantom Employee::base_salary
        // field — now takes the already-resolved real base salary.
        $hourlyRate = $baseSalary / 160;

        return [
            'hours'       => $overtimeHours,
            'hourly_rate' => $hourlyRate,
            'multiplier'  => 1.5,
            'total'       => $overtimeHours * $hourlyRate * 1.5,
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
        // Chantier 8.3: `hr_employee_loans` is a dead stub table from the
        // same early-scaffold origin as the old `hr_timesheets` read above
        // — confirmed via a repo-wide grep that no model, controller, or
        // UI anywhere in this app ever writes an employee loan record,
        // unlike overtime (which has real TimesheetEntry hours to derive
        // from above). Building a full loan-management feature
        // (application, approval, disbursement, repayment schedule) from
        // scratch is out of scope for this bug-fix chantier — left at the
        // safe 0 fallback this already degrades to, same fallback-first
        // pattern used throughout this app (e.g. Strategy's training_roi
        // ratio).
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
