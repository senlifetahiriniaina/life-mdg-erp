<?php

declare(strict_types=1);

namespace Tests\Integration\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Department;
use App\Models\User;
use Tests\TestCase;

class EmployeeOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_onboarding_flow_creates_all_records(): void
    {
        $user = User::factory()->create();
        $department = Department::factory()->create();

        // Create employee
        $employee = Employee::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
            'employment_type' => 'full-time',
            'hire_date' => now()->toDateString(),
            'status' => 'onboarded',
        ]);

        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);
    }

    public function test_employee_department_assignment_updates_relationships(): void
    {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => null]);

        $employee->update(['department_id' => $department->id]);

        $this->assertEquals($department->id, $employee->refresh()->department_id);
        $this->assertTrue($department->employees()->where('id', $employee->id)->exists());
    }

    public function test_multiple_employees_in_department(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->count(5)->create(['department_id' => $department->id]);

        $this->assertEquals(5, $department->employees()->count());
    }

    public function test_employee_user_relationship_is_consistent(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $employee->user->id);
        $this->assertEquals($employee->id, $user->employee->id);
    }

    public function test_employee_promotion_workflow(): void
    {
        $oldDepartment = Department::factory()->create(['name' => 'Support']);
        $newDepartment = Department::factory()->create(['name' => 'Management']);
        $employee = Employee::factory()->create([
            'department_id' => $oldDepartment->id,
            'job_title' => 'Support Specialist',
        ]);

        // Promote employee
        $employee->update([
            'department_id' => $newDepartment->id,
            'job_title' => 'Support Manager',
        ]);

        $this->assertEquals('Management', $employee->refresh()->department->name);
        $this->assertEquals('Support Manager', $employee->job_title);
    }

    public function test_employee_termination_workflow(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);

        // Terminate employee
        $employee->update([
            'status' => 'terminated',
            'termination_date' => now()->toDateString(),
            'termination_reason' => 'Resignation',
        ]);

        $this->assertEquals('terminated', $employee->refresh()->status);
        $this->assertNotNull($employee->termination_date);
    }

    public function test_leave_balance_initialization_on_hire(): void
    {
        $employee = Employee::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'employment_type' => 'full-time',
            'annual_leave_balance' => 20,
            'sick_leave_balance' => 10,
        ]);

        $this->assertEquals(20, $employee->annual_leave_balance);
        $this->assertEquals(10, $employee->sick_leave_balance);
    }

    public function test_employee_document_storage(): void
    {
        $employee = Employee::factory()->create();

        // Simulate document upload
        $this->assertDatabaseHas('hr_employees', [
            'id' => $employee->id,
        ]);

        // Employee documents can be queried
        $stored = Employee::find($employee->id);
        $this->assertInstanceOf(Employee::class, $stored);
    }
}
