<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\SalaryComponent;

class PayrollService
{
    /**
     * Create a new payroll run for a given tenant and period.
     */
    public function createRun(int $tenantId, string $period, string $currency = 'XOF', int $processedBy = 0): PayrollRun
    {
        return PayrollRun::create([
            'tenant_id'    => $tenantId,
            'period'       => $period,
            'status'       => 'draft',
            'currency'     => $currency,
            'processed_by' => $processedBy,
        ]);
    }

    /**
     * Process a payroll run: compute totals from payslips and mark as processing.
     */
    public function processRun(PayrollRun $run): PayrollRun
    {
        $totals = Payslip::where('payroll_run_id', $run->id)->selectRaw(
            'SUM(gross_salary) as total_gross, SUM(total_deductions) as total_deductions, SUM(net_salary) as total_net'
        )->first();

        $run->update([
            'status'           => 'processing',
            'total_gross'      => $totals->total_gross ?? 0,
            'total_deductions' => $totals->total_deductions ?? 0,
            'total_net'        => $totals->total_net ?? 0,
            'processed_at'     => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Validate a payroll run (manager approval step).
     */
    public function validateRun(PayrollRun $run): PayrollRun
    {
        $run->update([
            'status'       => 'validated',
            'validated_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Mark all payslips and the run as paid.
     */
    public function markAsPaid(PayrollRun $run): PayrollRun
    {
        Payslip::where('payroll_run_id', $run->id)
            ->where('status', 'draft')
            ->update(['status' => 'paid', 'paid_at' => now()]);

        $run->update(['status' => 'paid']);

        return $run->fresh();
    }

    /**
     * Get active salary components for a tenant.
     */
    public function getActiveComponents(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return SalaryComponent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('component_type')
            ->orderBy('name')
            ->get();
    }

    /**
     * Compute gross, deductions, and net salary for a given employee
     * based on a base salary and the active salary components.
     */
    public function computeSalary(int $tenantId, float $baseSalary): array
    {
        $components   = $this->getActiveComponents($tenantId);
        $earnings     = 0.0;
        $deductions   = 0.0;
        $breakdown    = [];

        foreach ($components as $component) {
            $amount = match ($component->calculation_type) {
                'fixed'      => (float) $component->amount,
                'percentage' => $baseSalary * ((float) $component->rate / 100),
                default      => 0.0,
            };

            $breakdown[] = [
                'name'   => $component->name,
                'type'   => $component->component_type,
                'amount' => round($amount, 2),
            ];

            if ($component->component_type === 'earning') {
                $earnings += $amount;
            } elseif (in_array($component->component_type, ['deduction', 'statutory'], true)) {
                $deductions += $amount;
            }
        }

        $gross = $baseSalary + $earnings;
        $net   = $gross - $deductions;

        return [
            'base_salary'      => $baseSalary,
            'earnings'         => round($earnings, 2),
            'gross_salary'     => round($gross, 2),
            'total_deductions' => round($deductions, 2),
            'net_salary'       => round($net, 2),
            'breakdown'        => $breakdown,
        ];
    }

    /**
     * Get summary statistics for a tenant.
     */
    public function getStats(int $tenantId): array
    {
        return [
            'runs_draft'      => PayrollRun::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'runs_validated'  => PayrollRun::where('tenant_id', $tenantId)->where('status', 'validated')->count(),
            'runs_paid'       => PayrollRun::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
            'total_payslips'  => Payslip::where('tenant_id', $tenantId)->count(),
            'payslips_paid'   => Payslip::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
            'last_run'        => PayrollRun::where('tenant_id', $tenantId)
                ->orderByDesc('period')
                ->first()?->only(['id', 'period', 'status', 'total_net', 'currency']),
        ];
    }
}
