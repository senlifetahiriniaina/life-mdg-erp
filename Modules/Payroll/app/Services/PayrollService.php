<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\SalaryComponent;

class PayrollService
{
    /**
     * Create a new payroll run for a given tenant and period.
     *
     * Chantier 19 Lot 2: CLAUDE.md's Chantier 10 note documents this whole
     * class as having "zero consumers anywhere, not even a test... left
     * alone". That claim is stale — confirmed via a repo-wide grep that
     * Modules\Workflow\Services\Actions\Phase52ActionHandler::
     * generatePayrollRun() (the real handler behind the registered
     * 'payroll.generate_run' Workflow node type, dispatched through
     * WorkflowEngineService::executeAction()) genuinely calls
     * app(PayrollService::class)->createRun() — a real, live, currently-
     * reachable automation action, not dead code. Its plain
     * PayrollRun::create() had no idempotency check at all, unlike every
     * other PayrollRun-creating path in this module
     * (PayrollIntegrationService::generatePayslip() already does a
     * where(tenant_id,period)->first() ?? create() against this exact
     * ['tenant_id','period']-unique model) — a second automation trigger
     * for the same tenant+period (a real scenario: a re-run automation, or
     * a workflow firing after a payroll-officer already generated payslips
     * through the normal UI, which creates its own PayrollRun) would throw
     * a UniqueConstraintViolationException. Not a hypothetical crash risk
     * for an end user (the caller already wraps this in try/catch and
     * degrades to a logged {status:error}), but a real correctness gap for
     * an automation whose whole point is safe re-triggering. Fixed to the
     * same firstOrCreate-shaped idempotency already established elsewhere
     * in this module.
     *
     * $period arrives here as a caller-supplied string in either 'Y-m'
     * shape (Phase52ActionHandler::generatePayrollRun() passes
     * $params['period'] ?? now()->format('Y-m'), e.g. "2026-05") or a full
     * date. Eloquent's `date` cast on PayrollRun::period normalizes
     * whatever gets written on save (Carbon::parse('2026-05') itself
     * already resolves to '2026-05-01'), but a raw where('period', $period)
     * lookup compares the UNCAST string against the stored 'Y-m-d' value —
     * "2026-05" would never match a stored "2026-05-01", making the
     * idempotency check above silently useless for exactly the caller that
     * needed it. Normalized explicitly to the same 'Y-m-d' shape
     * PayrollIntegrationService::generatePayslip() already stores.
     */
    public function createRun(int $tenantId, string $period, string $currency = 'XOF', int $processedBy = 0): PayrollRun
    {
        $periodDate = Carbon::parse($period)->startOfMonth()->toDateString();

        return PayrollRun::where('tenant_id', $tenantId)
            ->whereDate('period', $periodDate)
            ->first()
            ?? PayrollRun::create([
                'tenant_id'    => $tenantId,
                'period'       => $periodDate,
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
