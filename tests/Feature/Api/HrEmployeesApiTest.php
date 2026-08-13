<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/hr/employees')->assertUnauthorized();
    $this->postJson('/api/v1/hr/employees', [])->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated employees', function () {
     $user = actingAsUser('employee');
    Employee::factory()->count(3)->create();
        $response = $this
        ->getJson('/api/v1/hr/employees')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'per_page']]);
});

test('index returns all employees', function () {
     $user = actingAsUser('employee');
    Employee::factory()->count(4)->create();
        $response = $this
        ->getJson('/api/v1/hr/employees')
        ->assertOk()
        ->assertJsonCount(5, 'data'); // 4 created + 1 from actingAsUser
});

test('index filters by status', function () {
     $user = actingAsUser('employee');
    Employee::factory()->count(3)->create(['status' => 'active']);
    Employee::factory()->terminated()->create();
        $response = $this
        ->getJson('/api/v1/hr/employees?status=active')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('index filters by department', function () {
     $user = actingAsUser('employee');
    $department = Department::factory()->create();
    Employee::factory()->count(2)->create(['department_id' => $department->id]);
    Employee::factory()->create(['department_id' => null]);
        $response = $this
        ->getJson("/api/v1/hr/employees?department_id={$department->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index searches by name', function () {
     $user = actingAsUser('employee');
    Employee::factory()->create(['first_name' => 'UniqueFirst', 'last_name' => 'UniqueLast']);
    Employee::factory()->count(2)->create();
        $response = $this
        ->getJson('/api/v1/hr/employees?search=UniqueFirst')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create an employee', function () {
     $user = actingAsUser('employee');
     $department = \Modules\HR\Models\Department::factory()->create();
     $position = \Modules\HR\Models\JobPosition::factory()->create();
        $response = $this
        ->postJson('/api/v1/hr/employees', [
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'email'           => 'john.doe@company.com',
            'employee_number' => 'EMP001',
            'hire_date'       => '2024-01-15',
            'employment_type' => 'full_time',
            'department_id'   => $department->id,
            'job_position_id' => $position->id,
        ])
        ->assertCreated()
        ->assertJsonFragment(['first_name' => 'John', 'last_name' => 'Doe']);

    expect(Employee::where('email', 'john.doe@company.com')->exists())->toBeTrue();
});

test('create requires first_name last_name email employee_number and hire_date', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/hr/employees', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'employee_number', 'hire_date']);
});

test('create rejects duplicate email', function () {
     $user = actingAsUser('employee');
    Employee::factory()->create(['email' => 'taken@company.com']);
        $response = $this
        ->postJson('/api/v1/hr/employees', [
            'first_name'      => 'Another',
            'last_name'       => 'Person',
            'email'           => 'taken@company.com',
            'employee_number' => 'EMP999',
            'hire_date'       => '2024-01-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('create rejects duplicate employee number', function () {
     $user = actingAsUser('employee');
    Employee::factory()->create(['employee_number' => 'EMP-DUPE']);
        $response = $this
        ->postJson('/api/v1/hr/employees', [
            'first_name'      => 'New',
            'last_name'       => 'Person',
            'email'           => 'new@company.com',
            'employee_number' => 'EMP-DUPE',
            'hire_date'       => '2024-01-15',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_number']);
});

test('create rejects invalid employment type', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/hr/employees', [
            'first_name'      => 'X',
            'last_name'       => 'Y',
            'email'           => 'xy@company.com',
            'employee_number' => 'EMP-XY',
            'hire_date'       => '2024-01-15',
            'employment_type' => 'alien',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_type']);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show an employee', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create();
        $response = $this
        ->getJson("/api/v1/hr/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $employee->id]);
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update an employee', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create(['first_name' => 'Old']);
        $response = $this
        ->putJson("/api/v1/hr/employees/{$employee->id}", ['first_name' => 'New'])
        ->assertOk()
        ->assertJsonFragment(['first_name' => 'New']);

    expect($employee->fresh()->first_name)->toBe('New');
});

test('can terminate an employee', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create(['status' => 'active']);
        $response = $this
        ->putJson("/api/v1/hr/employees/{$employee->id}", [
            'status'           => 'terminated',
            'termination_date' => '2025-03-01',
        ])
        ->assertOk()
        ->assertJsonFragment(['status' => 'terminated']);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete an employee', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create();
        $response = $this
        ->deleteJson("/api/v1/hr/employees/{$employee->id}")
        ->assertNoContent();

    expect(Employee::find($employee->id))->toBeNull();
});

// ── Departments ───────────────────────────────────────────────────────────────

test('can list departments', function () {
     $user = actingAsUser('employee');
    Department::factory()->count(3)->create();
    $response = $this
        ->getJson('/api/v1/hr/departments')
        ->assertOk();

    // may be paginated (with 'data' key) or a plain collection
    $json = $response->json();
    $items = $json['data'] ?? $json;
    expect(count($items))->toBe(3);
});

test('can create a department', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/hr/departments', [
            'name' => 'Engineering',
            'code' => 'ENG',
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Engineering']);
});

// ── Leaves ────────────────────────────────────────────────────────────────────

test('can create a leave request', function () {
     $user = actingAsUser('employee');
    $employee  = Employee::factory()->create();
    $leaveType = LeaveType::factory()->create();
        $response = $this
        ->postJson('/api/v1/hr/leaves', [
            'employee_id'   => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date'    => '2025-06-01',
            'end_date'      => '2025-06-05',
            'reason'        => 'Vacation',
        ])
        ->assertCreated()
        ->assertJsonFragment(['reason' => 'Vacation']);
});

test('can approve a leave request', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create();
    $leave    = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'pending',
    ]);
        $response = $this
        ->postJson("/api/v1/hr/leave-requests/{$leave->id}/approve")
        ->assertOk()
        ->assertJsonFragment(['status' => 'approved']);
});

test('can reject a leave request', function () {
     $user = actingAsUser('employee');
    $employee = Employee::factory()->create();
    $leave    = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'pending',
    ]);
        $response = $this
        ->postJson("/api/v1/hr/leave-requests/{$leave->id}/reject", ['rejection_reason' => 'Busy period'])
        ->assertOk()
        ->assertJsonFragment(['status' => 'rejected']);
});
