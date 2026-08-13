<?php

use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;

describe('Employee API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list all employees', function () {
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/employees');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'first_name', 'last_name', 'email']]]);
    });

    test('can create an employee', function () {
        $department = Department::factory()->create();
        $jobPosition = JobPosition::factory()->create();

        $data = [
            'employee_number' => 'EMP001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
            'job_position_id' => $jobPosition->id,
            'hire_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/hr/employees', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'John');

        $this->assertDatabaseHas('hr_employees', ['employee_number' => 'EMP001']);
    });

    test('can update an employee', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/hr/employees/{$employee->id}", [
                'first_name' => 'Updated',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
            'first_name' => 'Updated',
        ]);
    });

    test('can delete an employee', function () {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/hr/employees/{$employee->id}");

        $response->assertStatus(204);
    });

    test('can filter employees by department', function () {
        $dept = Department::factory()->create();
        Employee::factory()->create(['department_id' => $dept->id]);
        Employee::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/hr/employees?department={$dept->id}");

        $response->assertJsonCount(1, 'data');
    });

    test('can get HR metrics', function () {
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/hr/employees/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_employees',
                'departments',
                'pending_leaves',
                'open_positions',
            ]);
    });
});
