<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveApprovalLog;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * Chantier 31 — reconfirmation of the HR leave-request approval chain's 7
 * layers (route, contrôleur, vue, modèle, format de données, sécurité,
 * RBAC), empirical-execution methodology (see CLAUDE.md). Re-checks the two
 * bug classes CLAUDE.md documents as already-fixed for this exact area
 * (Chantier 8.3's employee_id-vs-user_id ID-space bug on LeaveRequestPolicy,
 * Chantier 19 Lot 2's Str::singular('leaves') === 'leaf' route-model-binding
 * bug) and finds a real, previously-undocumented headline bug in the same
 * area: LeaveController::approve() — the controller actually wired to
 * Leaves/Index.vue's real admin approve/reject buttons — wrote
 * $request->user()->id (a users.id) into hr_leave_requests.approved_by, a
 * column with a hard FK to hr_employees.id. Every real approval has been
 * one fatal SQLSTATE foreign-key-violation away from happening, invisible
 * in the pre-existing Chantier19HRReauditTest.php coverage only because
 * that test's fresh-RefreshDatabase user id happened to numerically
 * collide with the leave's own employee id — exactly the kind of
 * ID-coincidence trap this file's tests are built to not repeat (every
 * test below creates the leave's employee and the approving manager's
 * employee with deliberately different, non-colliding ids before
 * asserting on the specific value written).
 */
beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
});

function chantier31MakeManagerWithDistinctEmployee(int $padWithEmployees = 3): array
{
    // Pad the hr_employees table with unrelated rows first so the
    // manager's own Employee id can never numerically collide with the
    // leave-owner's employee id purely by fresh-DB autoincrement luck —
    // the exact blind spot that let the pre-fix bug hide in
    // Chantier19HRReauditTest.php.
    Employee::factory()->count($padWithEmployees)->create();

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $managerEmployee = Employee::factory()->create(['user_id' => $manager->id]);

    return [$manager, $managerEmployee];
}

// ── Headline bug: LeaveController::approve() FK-crash + wrong ID space ────
it('approving via the real Leaves/Index.vue endpoint writes the approving manager real employee id, not their users.id', function () {
    [$manager, $managerEmployee] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'days' => 3,
        'days_requested' => 3,
        'reason' => 'test',
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/approve");
    $resp->assertOk();

    $fresh = $leave->fresh(['approver']);
    expect($fresh->status)->toBe('approved');
    // The real, semantically-correct value: the approving manager's own
    // Employee id — not their users.id (would have thrown a foreign key
    // violation before this chantier's fix), and not the leave-owning
    // employee's own id either (a coincidence-masked wrong attribution).
    expect($fresh->approved_by)->toBe($managerEmployee->id);
    expect($fresh->approver)->not->toBeNull();
    expect($fresh->approver->id)->toBe($managerEmployee->id);
    expect($fresh->approved_at)->not->toBeNull();
});

it('rejecting via the real Leaves/Index.vue endpoint persists the typed rejection reason and attributes the rejecting manager', function () {
    [$manager, $managerEmployee] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'days' => 3,
        'days_requested' => 3,
        'reason' => 'test',
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/reject", [
        'rejection_reason' => 'Coverage insufficient during that period',
    ]);
    $resp->assertOk();

    $fresh = $leave->fresh(['approver']);
    expect($fresh->status)->toBe('rejected');
    // Before this chantier's fix, 'rejection_reason' was mass-assigned
    // straight onto the model — a key never in LeaveRequest::$fillable —
    // silently dropped despite the column physically existing on the
    // table. The real fillable/exposed column is approval_notes.
    expect($fresh->approval_notes)->toBe('Coverage insufficient during that period');
    expect($fresh->approved_by)->toBe($managerEmployee->id);
    expect($fresh->approver?->id)->toBe($managerEmployee->id);
    expect($fresh->approved_at)->not->toBeNull();

    // The API response itself must also surface the persisted reason, via
    // LeaveRequestResource's real 'approval_notes' key.
    expect($resp->json('approval_notes'))->toBe('Coverage insufficient during that period');
});

it('approve/reject on the real endpoint now also populates hr_leave_approval_log, matching the leave-requests/ sibling path', function () {
    [$manager, $managerEmployee] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'days' => 3,
        'days_requested' => 3,
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    expect(LeaveApprovalLog::where('leave_request_id', $leave->id)->count())->toBe(0);

    $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/approve")->assertOk();

    $log = LeaveApprovalLog::where('leave_request_id', $leave->id)->first();
    expect($log)->not->toBeNull();
    expect($log->approver_id)->toBe($managerEmployee->id);
    expect($log->approver_role)->toBe('manager');
    expect($log->action)->toBe('approved');
});

// ── Null-safe approver edge case: a manager with no hr_employees row ─────
it('a manager with no linked Employee record can still approve without crashing, and approved_by is left null rather than a wrong id', function () {
    Employee::factory()->count(2)->create();

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    // Deliberately no Employee::factory() for this manager.

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'days' => 3,
        'days_requested' => 3,
        'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/approve");
    $resp->assertOk();

    $fresh = $leave->fresh();
    expect($fresh->status)->toBe('approved');
    expect($fresh->approved_by)->toBeNull();
    // No approval log entry either — nothing real to attribute it to.
    expect(LeaveApprovalLog::where('leave_request_id', $leave->id)->count())->toBe(0);
});

// ── Re-confirm the route-model-binding fix (Chantier 19 Lot 2) is still ───
// genuinely in effect — the approve/update/destroy verbs must resolve the
// SPECIFIC record by id, not silently touch a fresh unbound instance.
it('leaves/{id}/approve genuinely targets the requested record, not an unrelated or unbound one', function () {
    [$manager] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $target = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'status' => 'pending',
    ]);
    $decoy = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(10)->toDateString(), 'end_date' => now()->addDays(11)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/hr/leaves/{$target->id}/approve")->assertOk();

    expect($target->fresh()->status)->toBe('approved');
    expect($decoy->fresh()->status)->toBe('pending');
    // No ghost row silently inserted by an unbound model's save().
    expect(LeaveRequest::count())->toBe(2);
});

it('leaves/{id} PUT genuinely updates the specific record referenced by id', function () {
    [$manager] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'reason' => 'original', 'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $this->withToken($token)->putJson("/api/v1/hr/leaves/{$leave->id}", ['reason' => 'updated reason'])->assertOk();

    expect($leave->fresh()->reason)->toBe('updated reason');
});

// ── Security/RBAC: only manager/hr-manager/admin may approve/reject ──────
it('a plain employee cannot approve or reject a leave request via the real endpoint', function () {
    $rando = User::factory()->create();
    $rando->assignRole('employee');
    Employee::factory()->create(['user_id' => $rando->id]);

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'status' => 'pending',
    ]);
    $token = $rando->createToken('t')->plainTextToken;

    $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/approve")->assertForbidden();
    $this->withToken($token)->postJson("/api/v1/hr/leaves/{$leave->id}/reject", ['rejection_reason' => 'x'])->assertForbidden();

    expect($leave->fresh()->status)->toBe('pending');
});

// ── Security: employee ID-space fix (Chantier 8.3) — own vs. someone ─────
// else's pending request, re-confirmed empirically via the real HTTP path.
it('an employee can update their own pending leave request', function () {
    $empUser = User::factory()->create();
    $empUser->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $empUser->id]);
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'reason' => 'mine', 'status' => 'pending',
    ]);
    $token = $empUser->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->putJson("/api/v1/hr/leaves/{$leave->id}", ['reason' => 'updated by owner']);
    $resp->assertOk();
    expect($leave->fresh()->reason)->toBe('updated by owner');
});

it('an employee cannot update another employee own pending leave request', function () {
    $empUser = User::factory()->create();
    $empUser->assignRole('employee');
    Employee::factory()->create(['user_id' => $empUser->id]);

    $otherEmployee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $otherEmployee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'reason' => 'not yours', 'status' => 'pending',
    ]);
    $token = $empUser->createToken('t')->plainTextToken;

    $this->withToken($token)->putJson("/api/v1/hr/leaves/{$leave->id}", ['reason' => 'hijacked'])->assertForbidden();
    expect($leave->fresh()->reason)->toBe('not yours');
});

// ── The other real, routed controller (leave-requests/*) — approve()  ────
// resolves via HRService too, confirm it is genuinely unbroken as well
// since a caller could reach either real API surface.
it('the sibling leave-requests/{id}/approve endpoint also correctly attributes the approving manager employee', function () {
    [$manager, $managerEmployee] = chantier31MakeManagerWithDistinctEmployee();

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
        'days' => 2, 'days_requested' => 2, 'status' => 'pending',
    ]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve");
    $resp->assertOk();

    $fresh = $leave->fresh(['approver']);
    expect($fresh->status)->toBe('approved');
    expect($fresh->approved_by)->toBe($managerEmployee->id);
});

// ── Data format: LeaveRequest::store() via the real 'leaves' POST route ──
// (the one Leaves/Index.vue's create dialog actually calls) now populates
// days_requested consistently with the sibling leave-requests/ path.
it('creating a leave request via the real admin leaves POST route populates both days and days_requested', function () {
    [$manager] = chantier31MakeManagerWithDistinctEmployee();
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create(['is_active' => true]);
    $token = $manager->createToken('t')->plainTextToken;

    $resp = $this->withToken($token)->postJson('/api/v1/hr/leaves', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'reason' => 'one day',
    ]);
    $resp->assertCreated();

    $created = LeaveRequest::find($resp->json('id'));
    expect($created->days)->toBeGreaterThan(0);
    expect((float) $created->days_requested)->toBeGreaterThan(0.0);
    expect((float) $created->days_requested)->toBe((float) $created->days);
});

// ── Leave-balance side effect layer: approving a request is reflected in ──
// the real, reachable self-service balance endpoint (which sums 'days',
// not the orphaned hr_leave_balances table — see CLAUDE.md's already-
// documented note on that table having no writer since a prior chantier).
it('approving a leave request is reflected in the real me/leave-balance endpoint', function () {
    [$manager] = chantier31MakeManagerWithDistinctEmployee();

    $empUser = User::factory()->create();
    $empUser->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $empUser->id]);
    $leaveType = LeaveType::factory()->create(['is_active' => true, 'days_per_year' => 20]);
    $leave = LeaveRequest::create([
        'employee_id' => $employee->id, 'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
        'days' => 5, 'days_requested' => 5, 'status' => 'pending',
    ]);

    $mgrToken = $manager->createToken('t')->plainTextToken;
    $this->withToken($mgrToken)->postJson("/api/v1/hr/leaves/{$leave->id}/approve")->assertOk();

    // Laravel's Sanctum RequestGuard memoizes the resolved user for the
    // lifetime of the test's container — issuing a second request as a
    // different user within the same test needs the guards forgotten
    // first, or the employee's own request below would still silently
    // resolve as the manager from the call above.
    $this->app['auth']->forgetGuards();

    $empToken = $empUser->createToken('t')->plainTextToken;
    $balanceResp = $this->withToken($empToken)->getJson('/api/v1/hr/me/leave-balance');
    $balanceResp->assertOk();

    $row = collect($balanceResp->json())->firstWhere('id', $leaveType->id);
    expect($row)->not->toBeNull();
    expect((float) $row['days_taken'])->toBe(5.0);
    expect((float) $row['days_remaining'])->toBe(15.0);
});

// ── Vue layer: confirm the URL+verb the real page calls actually exist ───
// and behave as Leaves/Index.vue expects (already spot-checked individually
// above; this asserts the routes are genuinely registered with the correct
// parameter binding as a single cross-check).
it('every URL+verb Leaves/Index.vue calls is a real, registered route bound to leaveRequest', function () {
    $router = app('router');

    $approve = $router->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/hr/leaves/1/approve', 'POST'));
    expect($approve->parameterNames())->toBe(['leaveRequest']);

    $reject = $router->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/hr/leaves/1/reject', 'POST'));
    expect($reject->parameterNames())->toBe(['leaveRequest']);

    $update = $router->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/hr/leaves/1', 'PUT'));
    expect($update->parameterNames())->toBe(['leaveRequest']);

    $destroy = $router->getRoutes()->match(\Illuminate\Http\Request::create('/api/v1/hr/leaves/1', 'DELETE'));
    expect($destroy->parameterNames())->toBe(['leaveRequest']);
});
