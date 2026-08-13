<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * HR → Payroll Action Handler
 *
 * Handles all actions in the HR→Payroll workflow chain (WF-010 to WF-015).
 * Each method is invoked by WorkflowEngineService when the corresponding
 * action_key is matched during workflow execution.
 */
class HrPayrollActionHandler
{
    // ─── Payroll Actions ───────────────────────────────────────────────────────

    /**
     * action: payroll.adjust_for_leave
     *
     * Calculate deduction/adjustment for payroll based on approved leave.
     * - Paid leave (congé payé)    : no deduction, flag leave days in payslip
     * - Unpaid leave (congé sans solde) : deduct daily_rate × days
     * - Sick leave (maladie)       : depends on company policy (default: 3-day grace, then unpaid)
     *
     * @param array<string,mixed> $params  Workflow action params (e.g. grace_days for sick)
     * @param array<string,mixed> $context employee_id, leave_type (paid|unpaid|sick), days, period
     *
     * @return array<string,mixed>
     */
    public function adjustForLeave(array $params, array $context): array
    {
        $employeeId = $context['employee_id'] ?? null;
        $leaveType  = $context['leave_type']  ?? 'paid';
        $days       = (int) ($context['days']   ?? 0);
        $period     = $context['period']        ?? now()->format('Y-m');
        $dailyRate  = (float) ($context['daily_rate'] ?? 0.0);

        if (! $employeeId || $days <= 0) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        $deduction     = 0.0;
        $deductedDays  = 0;
        $note          = '';

        switch ($leaveType) {
            case 'unpaid':
                $deduction    = $dailyRate * $days;
                $deductedDays = $days;
                $note         = "Congé sans solde: {$days} jour(s) × {$dailyRate} XOF/jour";
                break;

            case 'sick':
                $graceDays      = (int) ($params['sick_grace_days'] ?? 3);
                $billableDays   = max(0, $days - $graceDays);
                $deduction      = $dailyRate * $billableDays;
                $deductedDays   = $billableDays;
                $note           = "Maladie: {$graceDays}j franchise, {$billableDays}j déduit(s) × {$dailyRate} XOF/jour";
                break;

            case 'paid':
            default:
                $note = "Congé payé: {$days} jour(s) — aucune déduction";
                break;
        }

        $adjustment = [
            'employee_id'    => $employeeId,
            'period'         => $period,
            'leave_type'     => $leaveType,
            'leave_days'     => $days,
            'deducted_days'  => $deductedDays,
            'deduction_xof'  => round($deduction, 2),
            'note'           => $note,
            'applied_at'     => now()->toIso8601String(),
        ];

        Log::info('[WF-010] payroll.adjust_for_leave', $adjustment);

        // TODO: persist to payroll_adjustments table (Modules/HR/Payroll)
        return ['status' => 'success', 'adjustment' => $adjustment];
    }

    /**
     * action: payroll.add_overtime
     *
     * Add validated overtime hours to the current payroll period.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, hours, rate_multiplier (1.25|1.5|2.0), period
     *
     * @return array<string,mixed>
     */
    public function addOvertime(array $params, array $context): array
    {
        $employeeId     = $context['employee_id']     ?? null;
        $hours          = (float) ($context['hours']          ?? 0.0);
        $rateMultiplier = (float) ($context['rate_multiplier'] ?? 1.25);
        $period         = $context['period']             ?? now()->format('Y-m');
        $hourlyRate     = (float) ($context['hourly_rate'] ?? 0.0);

        if (! $employeeId || $hours <= 0) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        $allowedMultipliers = [1.25, 1.5, 2.0];
        if (! in_array($rateMultiplier, $allowedMultipliers, true)) {
            $rateMultiplier = 1.25;
        }

        $overtimePay = $hourlyRate * $hours * $rateMultiplier;

        $record = [
            'employee_id'     => $employeeId,
            'period'          => $period,
            'overtime_hours'  => $hours,
            'rate_multiplier' => $rateMultiplier,
            'hourly_rate'     => $hourlyRate,
            'overtime_pay'    => round($overtimePay, 2),
            'applied_at'      => now()->toIso8601String(),
        ];

        Log::info('[WF-012] payroll.add_overtime', $record);

        // TODO: persist to payroll_overtime table
        return ['status' => 'success', 'overtime' => $record];
    }

    /**
     * action: payroll.enroll_new_employee
     *
     * Create payroll record when onboarding is completed.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, salary, payment_method (bank|mobile_money), start_date
     *
     * @return array<string,mixed>
     */
    public function enrollNewEmployee(array $params, array $context): array
    {
        $employeeId    = $context['employee_id']    ?? null;
        $salary        = (float) ($context['salary']        ?? 0.0);
        $paymentMethod = $context['payment_method'] ?? 'bank';
        $startDate     = $context['start_date']     ?? now()->toDateString();
        $currency      = $context['currency']       ?? 'XOF';

        if (! $employeeId || $salary <= 0) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        $allowedMethods = ['bank', 'mobile_money', 'cash'];
        if (! in_array($paymentMethod, $allowedMethods, true)) {
            $paymentMethod = 'bank';
        }

        $enrollment = [
            'employee_id'    => $employeeId,
            'gross_salary'   => round($salary, 2),
            'currency'       => $currency,
            'payment_method' => $paymentMethod,
            'start_date'     => $startDate,
            'first_payroll'  => Carbon::parse($startDate)->endOfMonth()->toDateString(),
            'enrolled_at'    => now()->toIso8601String(),
        ];

        Log::info('[WF-011] payroll.enroll_new_employee', $enrollment);

        // TODO: persist to payroll_profiles table
        return ['status' => 'success', 'enrollment' => $enrollment];
    }

    /**
     * action: payroll.calculate_final_settlement
     *
     * Calculate final payroll settlement (solde de tout compte) for departing employee.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, last_working_day, unused_leave_days
     *
     * @return array<string,mixed>
     */
    public function calculateFinalSettlement(array $params, array $context): array
    {
        $employeeId     = $context['employee_id']      ?? null;
        $lastDay        = $context['last_working_day'] ?? now()->toDateString();
        $unusedLeaveDays = (int) ($context['unused_leave_days'] ?? 0);
        $dailyRate      = (float) ($context['daily_rate'] ?? 0.0);

        if (! $employeeId) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        $leaveIndemnity   = $dailyRate * $unusedLeaveDays;
        $proRataLastMonth = (float) ($context['pro_rata_salary'] ?? 0.0);

        $settlement = [
            'employee_id'         => $employeeId,
            'last_working_day'    => $lastDay,
            'unused_leave_days'   => $unusedLeaveDays,
            'leave_indemnity'     => round($leaveIndemnity, 2),
            'pro_rata_last_month' => round($proRataLastMonth, 2),
            'total_settlement'    => round($leaveIndemnity + $proRataLastMonth, 2),
            'currency'            => 'XOF',
            'calculated_at'       => now()->toIso8601String(),
        ];

        Log::info('[WF-014] payroll.calculate_final_settlement', $settlement);

        return ['status' => 'success', 'settlement' => $settlement];
    }

    // ─── HR Actions ────────────────────────────────────────────────────────────

    /**
     * action: hr.create_disciplinary_record
     *
     * Record a disciplinary action (avertissement, mise en demeure, etc.).
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, type, reason, severity, issued_by
     *
     * @return array<string,mixed>
     */
    public function createDisciplinaryRecord(array $params, array $context): array
    {
        $employeeId = $context['employee_id'] ?? null;
        $type       = $context['type']        ?? 'avertissement';
        $reason     = $context['reason']      ?? '';
        $severity   = $context['severity']    ?? 'low';
        $issuedBy   = $context['issued_by']   ?? null;

        if (! $employeeId) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        $record = [
            'employee_id' => $employeeId,
            'type'        => $type,
            'reason'      => $reason,
            'severity'    => $severity,
            'issued_by'   => $issuedBy,
            'issued_at'   => now()->toIso8601String(),
        ];

        Log::info('[HR] hr.create_disciplinary_record', $record);

        // TODO: persist to hr_disciplinary_records table
        return ['status' => 'success', 'disciplinary_record' => $record];
    }

    /**
     * action: hr.notify_contract_expiry
     *
     * Send contract expiry alerts at 30, 15, and 7 days before expiry.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, contract_id, expiry_date, days_until_expiry
     *
     * @return array<string,mixed>
     */
    public function notifyContractExpiry(array $params, array $context): array
    {
        $employeeId      = $context['employee_id']       ?? null;
        $contractId      = $context['contract_id']       ?? null;
        $expiryDate      = $context['expiry_date']       ?? null;
        $daysUntilExpiry = (int) ($context['days_until_expiry'] ?? 0);

        if (! $employeeId || ! $expiryDate) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        // Only notify at the defined thresholds
        $thresholds = $params['alert_days'] ?? [30, 15, 7];
        if (! in_array($daysUntilExpiry, (array) $thresholds, false)) {
            return ['status' => 'skipped', 'reason' => "not_at_threshold (days_remaining={$daysUntilExpiry})"];
        }

        $urgency = match (true) {
            $daysUntilExpiry <= 7  => 'critical',
            $daysUntilExpiry <= 15 => 'warning',
            default                => 'info',
        };

        $notification = [
            'employee_id'      => $employeeId,
            'contract_id'      => $contractId,
            'expiry_date'      => $expiryDate,
            'days_remaining'   => $daysUntilExpiry,
            'urgency'          => $urgency,
            'message'          => "Le contrat de l'employé #{$employeeId} expire dans {$daysUntilExpiry} jour(s) ({$expiryDate}).",
            'notified_at'      => now()->toIso8601String(),
        ];

        Log::warning('[WF-013] hr.notify_contract_expiry', $notification);

        // TODO: dispatch notification via Modules/Messaging
        return ['status' => 'success', 'notification' => $notification];
    }

    // ─── IT Provisioning Actions ───────────────────────────────────────────────

    /**
     * action: it.provision_access
     *
     * Create user account, assign roles, and set permissions for new employee.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, department, role_template, email
     *
     * @return array<string,mixed>
     */
    public function provisionItAccess(array $params, array $context): array
    {
        $employeeId   = $context['employee_id']  ?? null;
        $department   = $context['department']   ?? 'general';
        $roleTemplate = $context['role_template'] ?? 'employee';
        $email        = $context['email']         ?? null;

        if (! $employeeId) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        // Role template → permissions mapping (simplified)
        $rolePermissions = $params['role_permissions'] ?? [];
        $defaultRoles = [
            'employee'    => ['view_own_payslips', 'view_own_leaves', 'submit_timesheets'],
            'manager'     => ['view_team_leaves', 'approve_leaves', 'view_team_reports'],
            'accountant'  => ['view_invoices', 'post_entries', 'view_reports'],
            'it_admin'    => ['manage_users', 'manage_permissions', 'view_all'],
        ];

        $assignedPermissions = $rolePermissions[$roleTemplate]
            ?? $defaultRoles[$roleTemplate]
            ?? $defaultRoles['employee'];

        $provisioning = [
            'employee_id'          => $employeeId,
            'email'                => $email,
            'department'           => $department,
            'role_template'        => $roleTemplate,
            'assigned_permissions' => $assignedPermissions,
            'account_created'      => true,
            'provisioned_at'       => now()->toIso8601String(),
        ];

        Log::info('[WF-011] it.provision_access', $provisioning);

        // TODO: call Security module to create user account with RBAC roles
        return ['status' => 'success', 'provisioning' => $provisioning];
    }

    /**
     * action: it.revoke_access
     *
     * Revoke all system access when an employee is offboarded or terminated.
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $context  employee_id, reason (resignation|termination|contract_end)
     *
     * @return array<string,mixed>
     */
    public function revokeItAccess(array $params, array $context): array
    {
        $employeeId = $context['employee_id'] ?? null;
        $reason     = $context['reason']      ?? 'offboarding';
        $immediate  = (bool) ($params['immediate'] ?? false);

        if (! $employeeId) {
            return ['status' => 'skipped', 'reason' => 'invalid_context'];
        }

        // For termination: immediate revocation; for resignation: end of notice period
        $revokeAt = $immediate
            ? now()->toIso8601String()
            : ($context['last_working_day'] ?? now()->toDateString());

        $revocation = [
            'employee_id'  => $employeeId,
            'reason'       => $reason,
            'revoke_at'    => $revokeAt,
            'immediate'    => $immediate,
            'revoked_at'   => now()->toIso8601String(),
            'sessions_terminated' => true,
            'tokens_revoked'      => true,
        ];

        Log::warning('[WF-014] it.revoke_access', $revocation);

        // TODO: call Auth module to invalidate tokens and disable account
        return ['status' => 'success', 'revocation' => $revocation];
    }

    // ─── Dispatcher ───────────────────────────────────────────────────────────

    /**
     * Dispatch an action by key to the appropriate handler method.
     *
     * @param string              $actionKey  e.g. 'payroll.adjust_for_leave'
     * @param array<string,mixed> $params     Workflow action configuration params
     * @param array<string,mixed> $context    Runtime trigger context
     *
     * @return array<string,mixed>
     */
    public function dispatch(string $actionKey, array $params, array $context): array
    {
        return match ($actionKey) {
            'payroll.adjust_for_leave'          => $this->adjustForLeave($params, $context),
            'payroll.add_overtime'              => $this->addOvertime($params, $context),
            'payroll.enroll_new_employee'       => $this->enrollNewEmployee($params, $context),
            'payroll.calculate_final_settlement'=> $this->calculateFinalSettlement($params, $context),
            'hr.create_disciplinary_record'     => $this->createDisciplinaryRecord($params, $context),
            'hr.notify_contract_expiry'         => $this->notifyContractExpiry($params, $context),
            'it.provision_access'               => $this->provisionItAccess($params, $context),
            'it.revoke_access'                  => $this->revokeItAccess($params, $context),
            default                             => ['status' => 'error', 'reason' => "unknown_action:{$actionKey}"],
        };
    }
}
