<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\PayrollRecord;

uses(RefreshDatabase::class);

function makeHrPortalEmployee(User $user): Employee
{
    // actingAsUser() auto-creates an employee for the user; replace it so this is the only one.
    Employee::where('user_id', $user->id)->delete();
    $dept = Department::factory()->create();
    $pos  = JobPosition::factory()->create(['department_id' => $dept->id]);
    return Employee::factory()->create([
        'user_id'         => $user->id,
        'department_id'   => $dept->id,
        'job_position_id' => $pos->id,
    ]);
}

// ── Auth ──────────────────────────────────────────────────────────────────────

test('portal profile requires authentication', function () {
    $this->getJson('/api/v1/hr/portal/profile')->assertUnauthorized();
});

test('portal leave-balance requires authentication', function () {
    $this->getJson('/api/v1/hr/portal/leave-balance')->assertUnauthorized();
});

// ── Profile ───────────────────────────────────────────────────────────────────

test('portal profile returns employee data for authenticated user', function () {
     $user = actingAsUser('employee');
    $employee = makeHrPortalEmployee($user);
        $response = $this
        ->getJson('/api/v1/hr/portal/profile')
        ->assertOk()
        ->assertJsonPath('id', $employee->id)
        ->assertJsonStructure(['department', 'job_position']);
});

test('portal profile returns 404 when no employee record', function () {
     $user = actingAsUser('employee');
    Employee::where('user_id', $user->id)->delete();
                $response = $this
        ->getJson('/api/v1/hr/portal/profile')
        ->assertNotFound();
});

// ── Leave balance ─────────────────────────────────────────────────────────────

test('leave balance returns array of types with remaining days', function () {
     $user = actingAsUser('employee');
    makeHrPortalEmployee($user);
    LeaveType::factory()->create(['days_per_year' => 25, 'is_active' => true]);
    $response = $this
        ->getJson('/api/v1/hr/portal/leave-balance')
        ->assertOk()
        ->assertJsonIsArray()
        ->json();

    expect($response[0])->toHaveKeys(['id', 'name', 'days_per_year', 'days_taken', 'days_remaining']);
});

test('leave balance days_remaining equals days_per_year minus days_taken', function () {
     $user = actingAsUser('employee');
    $employee = makeHrPortalEmployee($user);
    $type     = LeaveType::factory()->create(['days_per_year' => 20, 'is_active' => true]);
    $response = $this
        ->getJson('/api/v1/hr/portal/leave-balance')
        ->assertOk()
        ->json();

    $balance = collect($response)->firstWhere('id', $type->id);
    expect((float) $balance['days_remaining'])->toBe(20.0);
    expect((float) $balance['days_taken'])->toBe(0.0);
});

// ── Leave requests ────────────────────────────────────────────────────────────

test('portal leave requests returns paginated list', function () {
     $user = actingAsUser('employee');
    makeHrPortalEmployee($user);
        $response = $this
        ->getJson('/api/v1/hr/portal/leave-requests')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('portal can submit a leave request', function () {
     $user = actingAsUser('employee');
    makeHrPortalEmployee($user);
    $type = LeaveType::factory()->create(['is_active' => true]);
        $response = $this
        ->postJson('/api/v1/hr/portal/leave-requests', [
            'leave_type_id' => $type->id,
            'start_date'    => now()->addDays(7)->toDateString(),
            'end_date'      => now()->addDays(9)->toDateString(),
            'reason'        => 'Vacances familiales',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending');
});

test('leave request start_date must be today or future', function () {
     $user = actingAsUser('employee');
    makeHrPortalEmployee($user);
    $type = LeaveType::factory()->create(['is_active' => true]);
        $response = $this
        ->postJson('/api/v1/hr/portal/leave-requests', [
            'leave_type_id' => $type->id,
            'start_date'    => now()->subDays(5)->toDateString(),
            'end_date'      => now()->subDays(3)->toDateString(),
        ])
        ->assertUnprocessable();
});

// ── Payslips ──────────────────────────────────────────────────────────────────

test('portal payslips returns paginated list', function () {
     $user = actingAsUser('employee');
    $employee = makeHrPortalEmployee($user);
    PayrollRecord::factory()->count(3)->create(['employee_id' => $employee->id]);
        $response = $this
        ->getJson('/api/v1/hr/portal/payslips')
        ->assertOk()
        ->assertJsonStructure(['data', 'total'])
        ->assertJsonCount(3, 'data');
});

test('employee cannot access another employee payslip', function () {
    $user1  = User::factory()->create();
    $user2  = User::factory()->create();
    $emp1   = makeHrPortalEmployee($user1);
    $emp2   = makeHrPortalEmployee($user2);
    $payslip = PayrollRecord::factory()->create(['employee_id' => $emp2->id]);

    $this->actingAs($user1, 'sanctum')
        ->getJson("/api/v1/hr/portal/payslips/{$payslip->id}")
        ->assertForbidden();
});
