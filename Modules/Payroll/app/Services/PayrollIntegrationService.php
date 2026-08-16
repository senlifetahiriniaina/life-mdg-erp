<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use Modules\HR\Models\Employee;
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
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('termination_date')
            ->get();

        $records = [];
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $record = $this->generatePayslip($employee, $startDate, $endDate, $payrollCycle);
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
        string $payrollCycle = 'monthly'
    ): ?Payslip {
        // Idempotent — skip if already exists for this period
        $existing = Payslip::where('employee_id', $employee->id)
            ->whereDate('period', $startDate->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $tenantId = (int) ($employee->tenant_id ?? 0);
        $currency = $employee->salary_currency ?? 'XOF';

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

        $components = $this->calculateSalaryComponents($employee, $startDate, $endDate);
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
        Carbon $endDate
    ): array {
        $baseSalary = (float) ($employee->base_salary ?? $employee->monthly_salary ?? 0);

        $allowances = [
            'housing_allowance'     => (float) ($employee->housing_allowance ?? 0),
            'transport_allowance'   => (float) ($employee->transport_allowance ?? 0),
            'family_allowance'      => (float) ($employee->family_allowance ?? 0),
            'performance_allowance' => $this->calculatePerformanceAllowance($employee),
        ];
        $totalAllowances = array_sum($allowances);

        $overtime     = $this->calculateOvertime($employee, $startDate, $endDate);
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

    private function calculatePerformanceAllowance(Employee $employee): float
    {
        $rating     = $employee->latest_performance_rating ?? 3;
        $baseSalary = (float) ($employee->base_salary ?? $employee->monthly_salary ?? 0);

        return $baseSalary * match ($rating) {
            5       => 0.15,
            4       => 0.10,
            3       => 0.05,
            2       => 0.02,
            default => 0.0,
        };
    }

    private function calculateOvertime(Employee $employee, Carbon $startDate, Carbon $endDate): array
    {
        $overtimeHours = DB::table('hr_timesheets')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('overtime_hours');

        $hourlyRate = ((float) ($employee->base_salary ?? $employee->monthly_salary ?? 0)) / 160;

        return [
            'hours'       => (float) $overtimeHours,
            'hourly_rate' => $hourlyRate,
            'multiplier'  => 1.5,
            'total'       => (float) $overtimeHours * $hourlyRate * 1.5,
        ];
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
