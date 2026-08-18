<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;

/**
 * HR → Payroll Action Handler
 *
 * Handles all actions in the HR→Payroll workflow chain (WF-010 to WF-015).
 * Each method is invoked by WorkflowEngineService when the corresponding
 * action_key is matched during workflow execution.
 *
 * Chantier 10: every method below used to only Log::info()/warning() and
 * return a computed-but-never-persisted payload, each with a "TODO: persist
 * to <table that doesn't exist>" comment. Investigated what each concept's
 * real backing is in this app and wired directly onto it — see each
 * method's own docblock. Two (createDisciplinaryRecord, and the granular
 * per-adjustment ledger `adjustForLeave` used to imagine) genuinely have no
 * real backing anywhere in the app; per this chantier's constraints those
 * are left as documented gaps rather than inventing new tables/business
 * logic, with the TODO comment rewritten to explain why.
 */
class HrPayrollActionHandler
{
    public function __construct(
        private readonly NotificationActionHandler $notifier,
    ) {
    }

    // ─── Payroll Actions ───────────────────────────────────────────────────────

    /**
     * action: payroll.adjust_for_leave
     *
     * Calculate deduction/adjustment for payroll based on approved leave.
     * - Paid leave (congé payé)    : no deduction, flag leave days in payslip
     * - Unpaid leave (congé sans solde) : deduct daily_rate × days
     * - Sick leave (maladie)       : depends on company policy (default: 3-day grace, then unpaid)
     *
     * Chantier 10: this used to compute a preview and then just log it
     * ("TODO: persist to payroll_adjustments table" — a table that never
     * existed anywhere in the app). No separate ledger is needed: the real
     * source of truth is already persisted — the employee's own approved
     * Modules\HR\Models\LeaveRequest record that triggered this action in
     * the first place. Modules\Payroll\Services\PayrollIntegrationService::
     * calculateDeductions() now reads approved LeaveRequests directly for
     * the payslip's period (same unpaid/sick-minus-grace-days rule applied
     * here) at payslip-generation time, so nothing further needs to be
     * written here. This method's job is only to compute an immediate
     * preview for whoever approved the leave and notify them of the
     * expected impact — the actual payroll-time calculation is owned by
     * PayrollIntegrationService, not duplicated here.
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

        if ($deduction > 0) {
            $this->notifier->sendInAppNotification(
                [
                    'title_template' => 'Impact paie — congé employé #{{employee_id}}',
                    'body_template'  => '{{note}} Cet ajustement sera appliqué automatiquement à la génération du bulletin de paie {{period}}.',
                    'type'           => 'info',
                    'module'         => 'Payroll',
                ],
                array_merge($context, $adjustment, ['to' => 'payroll-officer']),
            );
        }

        return ['status' => 'success', 'adjustment' => $adjustment];
    }

    /**
     * action: payroll.add_overtime
     *
     * Add validated overtime hours to the current payroll period.
     *
     * Chantier 10: used to log-and-drop ("TODO: persist to payroll_overtime
     * table" — never existed). The real, live overtime data source in this
     * app is Modules\Timesheets\Models\TimesheetEntry — PayrollIntegrationService
     * ::calculateOvertime() already sums approved entries' hours_worked
     * beyond 160h/month for the period at payslip-generation time (see that
     * method's own docblock). This action represents already-validated
     * overtime (e.g. approved by a manager upstream in the workflow chain)
     * — persisting it as a real, approved TimesheetEntry feeds it into that
     * existing calculation instead of inventing a parallel ledger.
     * rate_multiplier is recorded on the entry's description for audit
     * purposes but not applied as a distinct per-entry rate:
     * calculateOvertime() already applies one flat 1.5x multiplier to ALL
     * overtime hours in the app (a pre-existing, established behavior, not
     * something this fix should special-case per request).
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

        $employee = Employee::find($employeeId);
        $entry    = null;

        if ($employee) {
            try {
                $entryDate = Carbon::parse($period . '-01')->endOfMonth();

                $entry = $employee->timesheetEntries()->create([
                    'tenant_id'    => $employee->user?->tenant_id,
                    'entry_date'   => $entryDate->toDateString(),
                    'hours_worked' => $hours,
                    'status'       => 'approved',
                    'description'  => "Heures supplémentaires validées (workflow WF-012, x{$rateMultiplier})",
                    'hourly_rate'  => $hourlyRate,
                    'approved_at'  => now(),
                ]);

                $record['timesheet_entry_id'] = $entry->id;
            } catch (\Throwable $e) {
                Log::error('[WF-012] payroll.add_overtime: failed to persist TimesheetEntry', [
                    'employee_id' => $employeeId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[WF-012] payroll.add_overtime', $record);

        return ['status' => 'success', 'overtime' => $record];
    }

    /**
     * action: payroll.enroll_new_employee
     *
     * Create payroll record when onboarding is completed.
     *
     * Chantier 10: used to log-and-drop ("TODO: persist to payroll_profiles
     * table" — never existed). The real per-employee salary source this app
     * actually reads at payslip time is Modules\HR\Models\EmployeeCompensation
     * (see PayrollIntegrationService::getCurrentCompensation() /
     * Modules\HR\Services\CompensationService) — creates the employee's
     * first compensation record there instead of a separate, redundant
     * "payroll profile" concept. payment_method has no real column
     * anywhere in this app (EmployeeCompensation has no payment-method
     * field, and no mobile-money/bank-transfer payout integration exists
     * for payroll yet) — kept in the returned payload for the caller/
     * notification but not persisted, since inventing a payout-method
     * column with no consumer would be the same "table nothing reads"
     * problem this fix is closing elsewhere.
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

        $employee = Employee::find($employeeId);

        if ($employee) {
            try {
                $compensation = EmployeeCompensation::create([
                    'employee_id'     => $employee->id,
                    'base_salary'     => round($salary, 2),
                    'currency'        => $currency,
                    'total_compensation' => round($salary, 2),
                    'effective_date'  => $startDate,
                    'notes'           => "Enrôlement initial (workflow WF-011, moyen de paiement: {$paymentMethod})",
                ]);

                $enrollment['employee_compensation_id'] = $compensation->id;
            } catch (\Throwable $e) {
                Log::error('[WF-011] payroll.enroll_new_employee: failed to persist EmployeeCompensation', [
                    'employee_id' => $employeeId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        Log::info('[WF-011] payroll.enroll_new_employee', $enrollment);

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
     * Chantier 10 — genuinely no real backing exists for this concept
     * anywhere in this app. Confirmed via a repo-wide grep: no
     * "disciplinary"/"warning"/"misconduct" model, table, migration,
     * controller, or Vue page exists in Modules/HR or anywhere else — this
     * is a real, standalone HR feature (issuing and tracking formal
     * warnings, with severity levels and an issuing manager) that was never
     * built in this app's "basique" HR scope (see CLAUDE.md's HR scope
     * note — recruitment/360°-review/training/succession were explicitly
     * cut, and disciplinary records were never part of the kept model
     * list either). Per this chantier's constraints, inventing a new
     * table/model for it is out of scope for a wiring pass — left as a
     * documented product-decision gap rather than built silently.
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

        // Not persisted — no disciplinary-record model/table exists anywhere
        // in this app's HR "basique" scope. See this method's docblock:
        // this is a documented product-decision gap (build a real feature,
        // or drop the action from the workflow chain), not a wiring bug.
        return ['status' => 'success', 'disciplinary_record' => $record, 'persisted' => false];
    }

    /**
     * action: hr.notify_contract_expiry
     *
     * Send contract expiry alerts at 30, 15, and 7 days before expiry.
     *
     * Chantier 10: used to only Log::warning() ("TODO: dispatch notification
     * via Modules/Messaging" — that module is explicitly out of this app's
     * 27-module scope, see CLAUDE.md's Scope section: Messaging was one of
     * the modules intentionally left out of the WideHalo extraction).
     * Dispatches a real in-app notification instead, via the same
     * NotificationActionHandler every 'notify.*' workflow action already
     * uses (writes to the real `notifications` table, no excluded module
     * involved).
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

        $employee = Employee::find($employeeId);
        $targets  = ['hr-manager'];
        if ($employee?->user_id) {
            $targets[] = $employee->user_id;
        }

        $dispatchResult = $this->notifier->sendInAppNotification(
            [
                'title_template' => 'Expiration de contrat — {{days_remaining}} jour(s)',
                'body_template'  => '{{message}}',
                'type'           => $urgency === 'critical' ? 'error' : $urgency,
                'module'         => 'HR',
            ],
            array_merge($context, $notification, ['to' => $targets]),
        );

        return ['status' => 'success', 'notification' => $notification, 'dispatch' => $dispatchResult];
    }

    // ─── IT Provisioning Actions ───────────────────────────────────────────────

    /**
     * action: it.provision_access
     *
     * Create user account, assign roles, and set permissions for new employee.
     *
     * Chantier 10: used to log-and-drop ("TODO: call Security module to
     * create user account with RBAC roles" — there is no separate
     * "Security module account service"; user accounts + spatie/
     * laravel-permission roles live on the real, single App\Models\User
     * model everywhere else in this app). Creates (or reuses) the real
     * User account, links it to the Employee record, and assigns a real
     * seeded role via Spatie's assignRole() — role_template is mapped to
     * the closest real role name from database/seeders/
     * RolesAndPermissionsSeeder.php (employee/manager/accountant all match
     * 1:1; it_admin maps to the real 'system-admin' role, since no
     * 'it-admin' role is seeded). The granular $rolePermissions/
     * $defaultRoles list below is kept as an informational hint in the
     * response payload only — RBAC in this app is enforced by seeded
     * role→permission assignments, not by handing out ad-hoc permission
     * lists per provisioning call.
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

        // Role template → permissions mapping (simplified, informational only —
        // see docblock above for why RBAC enforcement itself is via assignRole()).
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

        // Real seeded role name (RolesAndPermissionsSeeder.php) — it_admin has
        // no seeded 'it-admin' role, so it maps to the closest real
        // administrative role, 'system-admin'.
        $realRole = match ($roleTemplate) {
            'manager'    => 'manager',
            'accountant' => 'accountant',
            'it_admin'   => 'system-admin',
            default      => 'employee',
        };

        $employee = Employee::find($employeeId);
        $account  = false;
        $userId   = null;

        if ($employee) {
            $resolvedEmail = $email ?: $employee->email;
            $user = $employee->user;

            if (! $user && $resolvedEmail) {
                $user = User::where('email', $resolvedEmail)->first();
            }

            try {
                if (! $user && $resolvedEmail) {
                    $user = User::create([
                        'name'      => trim("{$employee->first_name} {$employee->last_name}"),
                        'first_name'=> $employee->first_name,
                        'last_name' => $employee->last_name,
                        'email'     => $resolvedEmail,
                        'password'  => Hash::make(str()->random(24)),
                        'is_active' => true,
                    ]);

                    $employee->update(['user_id' => $user->id]);
                }

                if ($user) {
                    $user->is_active = true;
                    $user->save();

                    if (! $user->hasRole($realRole)) {
                        $user->assignRole($realRole);
                    }

                    $account = true;
                    $userId  = $user->id;
                }
            } catch (\Throwable $e) {
                Log::error('[WF-011] it.provision_access: failed to create/update User account', [
                    'employee_id' => $employeeId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $provisioning = [
            'employee_id'          => $employeeId,
            'user_id'               => $userId,
            'email'                => $email,
            'department'           => $department,
            'role_template'        => $roleTemplate,
            'assigned_role'        => $realRole,
            'assigned_permissions' => $assignedPermissions,
            'account_created'      => $account,
            'provisioned_at'       => now()->toIso8601String(),
        ];

        Log::info('[WF-011] it.provision_access', $provisioning);

        return ['status' => 'success', 'provisioning' => $provisioning];
    }

    /**
     * action: it.revoke_access
     *
     * Revoke all system access when an employee is offboarded or terminated.
     *
     * Chantier 10: used to log-and-drop ("TODO: call Auth module to
     * invalidate tokens and disable account" — there is no separate "Auth
     * module"; Sanctum tokens and account status live directly on the real
     * App\Models\User model, same as it.provision_access above). For
     * immediate revocation, disables the real User account (is_active =
     * false) and deletes all its Sanctum tokens via the same $user->
     * tokens()->delete() call already used by Modules\Core\Http\Controllers
     * \Api\AccountController — no separate "Auth module" call needed. For a
     * non-immediate (notice-period) revocation, only logs/notifies today —
     * actually scheduling a future account disable would need a real job
     * dispatch mechanism this action has no queue/schedule wiring for yet;
     * left as an immediate-only real fix rather than half-building a
     * scheduling concept no other part of this action handles.
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

        $sessionsTerminated = false;
        $tokensRevoked       = false;

        if ($immediate) {
            $employee = Employee::find($employeeId);
            $user     = $employee?->user;

            if ($user) {
                try {
                    $user->is_active = false;
                    $user->save();

                    $user->tokens()->delete();

                    $sessionsTerminated = true;
                    $tokensRevoked      = true;
                } catch (\Throwable $e) {
                    Log::error('[WF-014] it.revoke_access: failed to disable User account', [
                        'employee_id' => $employeeId,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
        }

        $revocation = [
            'employee_id'  => $employeeId,
            'reason'       => $reason,
            'revoke_at'    => $revokeAt,
            'immediate'    => $immediate,
            'revoked_at'   => now()->toIso8601String(),
            'sessions_terminated' => $sessionsTerminated,
            'tokens_revoked'      => $tokensRevoked,
        ];

        Log::warning('[WF-014] it.revoke_access', $revocation);

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
