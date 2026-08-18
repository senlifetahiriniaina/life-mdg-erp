<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\Payroll\Services\PayrollIntegrationService;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Workflow\Models\WorkflowChainDefinition;
use Modules\Workflow\Services\Actions\HrPayrollActionHandler;
use Modules\Workflow\Services\WorkflowEngineService;

/*
 * Chantier 10 — HrPayrollActionHandler's 7 TODO stubs were wired to real
 * tables/models instead of logging-and-dropping, and the WorkflowEngineService
 * ::executeAction() dispatcher was fixed to actually route hr./it./payroll.*
 * (chain-action) keys to it. These tests exercise the real HTTP/model paths,
 * not just a code read.
 */

beforeEach(function () {
    $this->withoutMiddleware([
        \App\Http\Middleware\SecurityHeaders::class,
        \App\Http\Middleware\SessionSecurityMiddleware::class,
        \App\Http\Middleware\OutputEncodingMiddleware::class,
        \App\Http\Middleware\CsrfTokenMiddleware::class,
        \App\Http\Middleware\RequireMFAMiddleware::class,
        \App\Http\Middleware\IPAccessControlMiddleware::class,
        \App\Http\Middleware\RateLimitMiddleware::class,
    ]);
});

it('routes hr./it./payroll.* chain actions to HrPayrollActionHandler via the dispatcher instead of unknown_action', function () {
    $engine = app(WorkflowEngineService::class);

    $result = $engine->executeAction('hr.notify_contract_expiry', [], [
        'employee_id'        => 999999,
        'contract_id'        => 1,
        'expiry_date'        => now()->addDays(7)->toDateString(),
        'days_until_expiry'  => 7,
    ]);

    // Real dispatch reached the handler (invalid_context/skipped from the
    // handler itself, never the generic "Unknown action module" default).
    expect($result['reason'] ?? null)->not->toBe('Unknown action module: hr');
});

it('persists validated overtime as a real approved TimesheetEntry that feeds PayrollIntegrationService::calculateOvertime()', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    $handler = app(HrPayrollActionHandler::class);

    $period = now()->format('Y-m');

    $result = $handler->dispatch('payroll.add_overtime', [], [
        'employee_id'     => $employee->id,
        'hours'           => 12,
        'rate_multiplier' => 1.5,
        'period'          => $period,
        'hourly_rate'     => 2000,
    ]);

    expect($result['status'])->toBe('success');
    expect($result['overtime']['timesheet_entry_id'] ?? null)->not->toBeNull();

    $entry = TimesheetEntry::find($result['overtime']['timesheet_entry_id']);
    expect($entry)->not->toBeNull();
    expect((float) $entry->hours_worked)->toBe(12.0);
    expect($entry->status)->toBe('approved');
    expect($entry->employee_id)->toBe($employee->id);
});

it('persists new-employee enrollment as a real EmployeeCompensation record', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    $handler = app(HrPayrollActionHandler::class);

    $result = $handler->dispatch('payroll.enroll_new_employee', [], [
        'employee_id'    => $employee->id,
        'salary'         => 450000,
        'payment_method' => 'mobile_money',
        'start_date'     => now()->toDateString(),
        'currency'       => 'XOF',
    ]);

    expect($result['status'])->toBe('success');
    expect($result['enrollment']['employee_compensation_id'] ?? null)->not->toBeNull();

    $compensation = $employee->compensations()->latest('effective_date')->first();
    expect($compensation)->not->toBeNull();
    expect((float) $compensation->base_salary)->toBe(450000.0);
});

it('provisions a real User account with a real seeded role', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    $employee = Employee::factory()->create(['status' => 'active', 'user_id' => null]);
    $handler = app(HrPayrollActionHandler::class);

    $result = $handler->dispatch('it.provision_access', [], [
        'employee_id'   => $employee->id,
        'department'    => 'finance',
        'role_template' => 'accountant',
        'email'         => 'chantier10-' . $employee->id . '@example.test',
    ]);

    expect($result['status'])->toBe('success');
    expect($result['provisioning']['account_created'])->toBeTrue();

    $user = User::find($result['provisioning']['user_id']);
    expect($user)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->hasRole('accountant'))->toBeTrue();

    $employee->refresh();
    expect($employee->user_id)->toBe($user->id);
});

it('revokes real access immediately: disables the User and deletes Sanctum tokens', function () {
    $user = User::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['status' => 'active', 'user_id' => $user->id]);
    $user->createToken('test-token');

    expect($user->tokens()->count())->toBe(1);

    $handler = app(HrPayrollActionHandler::class);

    $result = $handler->dispatch('it.revoke_access', ['immediate' => true], [
        'employee_id' => $employee->id,
        'reason'      => 'termination',
    ]);

    expect($result['status'])->toBe('success');
    expect($result['revocation']['tokens_revoked'])->toBeTrue();

    $user->refresh();
    expect($user->is_active)->toBeFalse();
    expect($user->tokens()->count())->toBe(0);
});

it('leaves disciplinary records unpersisted (documented gap — no real model exists)', function () {
    $handler = app(HrPayrollActionHandler::class);

    $result = $handler->dispatch('hr.create_disciplinary_record', [], [
        'employee_id' => 1,
        'type'        => 'avertissement',
        'reason'      => 'Retard répété',
        'severity'    => 'low',
    ]);

    expect($result['status'])->toBe('success');
    expect($result['persisted'])->toBeFalse();
});

it('deducts unpaid leave days from the payslip via the real, already-persisted LeaveRequest — no separate ledger needed', function () {
    $employee = Employee::factory()->create(['status' => 'active']);
    $unpaidType = LeaveType::factory()->create(['is_paid' => false]);

    $start = now()->startOfMonth()->addDays(2);
    LeaveRequest::factory()->create([
        'employee_id'    => $employee->id,
        'leave_type_id'  => $unpaidType->id,
        'status'         => 'approved',
        'start_date'     => $start,
        'end_date'       => $start->copy()->addDays(4),
        'days'           => 5,
        'days_requested' => 5,
    ]);

    $service = app(PayrollIntegrationService::class);
    $deductions = $service->calculateDeductions($employee, 600000, now()->startOfMonth(), now()->endOfMonth());

    expect($deductions['deductions']['unpaid_leave_deduction'])->toBeGreaterThan(0);
    // 600000/30 * 5 days = 100000
    expect($deductions['deductions']['unpaid_leave_deduction'])->toBe(100000.0);
});

it('patches workflow_chain_executions/workflow_execution_steps with the real columns live code writes', function () {
    expect(\Illuminate\Support\Facades\Schema::hasColumns('workflow_chain_executions', [
        'workflow_definition_id', 'trigger_key', 'context_snapshot', 'started_at', 'completed_at', 'result_log', 'error_message',
    ]))->toBeTrue();

    expect(\Illuminate\Support\Facades\Schema::hasColumns('workflow_execution_steps', [
        'execution_id', 'step_index', 'action_key', 'input_context', 'output', 'duration_ms', 'executed_at', 'error_message',
    ]))->toBeTrue();
});
