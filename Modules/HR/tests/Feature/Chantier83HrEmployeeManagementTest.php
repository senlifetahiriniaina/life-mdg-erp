<?php

declare(strict_types=1);

use App\Models\User;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;

/**
 * Chantier 8.3 (HR): EmployeeManagementService's onboardEmployee()/
 * getEmployeeProfile() referenced Employee columns that don't exist
 * (department/position/salary — real columns are department_id/
 * job_position_id, no salary column at all) — fixed and routed for the
 * first time via EmployeeManagementController.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function hrEmployeeManagementUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('hr-manager');

    return $user;
}

test('onboarding an employee creates a real record with department/position FKs', function () {
    $user = hrEmployeeManagementUser();
    $dept = Department::factory()->create();
    $pos = JobPosition::factory()->create(['department_id' => $dept->id]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/hr/employees/onboard', [
        'first_name' => 'Voahangy',
        'last_name' => 'Razafy',
        'email' => 'voahangy.razafy@example.com',
        'hire_date' => now()->toDateString(),
        'department_id' => $dept->id,
        'job_position_id' => $pos->id,
    ]);

    $response->assertCreated();
    $employeeId = $response->json('employee_id');
    $employee = Employee::findOrFail($employeeId);
    expect($employee->department_id)->toBe($dept->id);
    expect($employee->job_position_id)->toBe($pos->id);
    expect($employee->status)->toBe('onboarding');
});

test('completing onboarding transitions status to active', function () {
    $user = hrEmployeeManagementUser();
    $employee = Employee::factory()->create(['status' => 'onboarding']);

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/employees/{$employee->id}/complete-onboarding")
        ->assertOk()
        ->assertJsonFragment(['status' => 'active']);

    expect($employee->fresh()->status)->toBe('active');
});

test('profile endpoint returns real department/position names, not raw ids', function () {
    $user = hrEmployeeManagementUser();
    $dept = Department::factory()->create(['name' => 'Engineering']);
    $pos = JobPosition::factory()->create(['department_id' => $dept->id, 'title' => 'Software Engineer']);
    $employee = Employee::factory()->create(['department_id' => $dept->id, 'job_position_id' => $pos->id]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/hr/employees/{$employee->id}/profile");

    $response->assertOk();
    expect($response->json('department'))->toBe('Engineering');
    expect($response->json('position'))->toBe('Software Engineer');
});

test('terminating an employee sets termination fields and status', function () {
    $user = hrEmployeeManagementUser();
    $employee = Employee::factory()->create(['status' => 'active']);

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/hr/employees/{$employee->id}/terminate", [
        'reason' => 'Resignation',
    ])->assertOk()->assertJsonFragment(['status' => 'terminated']);

    $fresh = $employee->fresh();
    expect($fresh->status)->toBe('terminated');
    expect($fresh->termination_reason)->toBe('Resignation');
});
