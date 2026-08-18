<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * Chantier 8.3 (HR): LeaveRequestController::update()/destroy() called no
 * authorize() at all — any authenticated user could edit or cancel any
 * other employee's pending leave request. Separately, LeaveRequestPolicy had
 * no update() override, so it fell through to BaseErpPolicy::update() ->
 * isAdminOrOwner(), which compared $model->employee_id (an hr_employees.id)
 * to $user->id (a users.id) — two different ID spaces that never match, so
 * an employee could never pass authorize('update', ...) on their OWN leave
 * request either. Both fixed: authorize() calls added, and
 * LeaveRequestPolicy::update() now compares against the leave request's
 * employee's real user_id.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function leaveRequestAuthzTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('employee can update their own pending leave request', function () {
    $user = leaveRequestAuthzTestUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $leaveType = LeaveType::factory()->create();
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $response = test()->actingAs($user, 'sanctum')->putJson("/api/v1/hr/leave-requests/{$leave->id}", [
        'reason' => 'Updated reason',
    ]);

    $response->assertOk();
});

test('employee cannot update another employee\'s pending leave request', function () {
    $user = leaveRequestAuthzTestUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
    $otherLeave = LeaveRequest::factory()->create([
        'employee_id' => $otherEmployee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $response = test()->actingAs($user, 'sanctum')->putJson("/api/v1/hr/leave-requests/{$otherLeave->id}", [
        'reason' => 'Trying to edit someone else\'s leave',
    ]);

    $response->assertForbidden();
});

test('employee cannot delete another employee\'s pending leave request', function () {
    $user = leaveRequestAuthzTestUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
    $otherLeave = LeaveRequest::factory()->create([
        'employee_id' => $otherEmployee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/hr/leave-requests/{$otherLeave->id}");

    $response->assertForbidden();
});

test('regular employee cannot approve their own leave request', function () {
    $user = leaveRequestAuthzTestUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $leaveType = LeaveType::factory()->create();
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve");

    $response->assertForbidden();
});

test('hr-manager can approve any employee\'s leave request', function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $manager = User::factory()->create();
    $manager->assignRole('hr-manager');

    $employee = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
    $leave = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $response = test()->actingAs($manager, 'sanctum')->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve");

    $response->assertOk();
});
