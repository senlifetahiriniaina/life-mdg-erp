<?php

declare(strict_types=1);

namespace Tests\Unit\Models\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Department;
use Modules\HR\Models\LeaveRequest;
use App\Models\User;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $employee->user);
        $this->assertEquals($user->id, $employee->user->id);
    }

    public function test_employee_belongs_to_department(): void
    {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $this->assertInstanceOf(Department::class, $employee->department);
        $this->assertEquals($department->id, $employee->department->id);
    }

    public function test_employee_has_many_leave_requests(): void
    {
        $employee = Employee::factory()->create();
        LeaveRequest::factory()->count(3)->create(['employee_id' => $employee->id]);

        $this->assertEquals(3, $employee->leaveRequests()->count());
    }

    public function test_employee_email_is_required(): void
    {
        $this->expectException(\Exception::class);
        Employee::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'employment_type' => 'full-time',
            // Missing email
        ]);
    }

    public function test_employee_scope_active_returns_only_active_employees(): void
    {
        Employee::factory()->count(3)->create(['status' => 'active']);
        Employee::factory()->count(2)->create(['status' => 'inactive']);

        $active = Employee::active()->count();

        $this->assertEquals(3, $active);
    }

    public function test_employee_scope_by_department(): void
    {
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        Employee::factory()->count(3)->create(['department_id' => $dept1->id]);
        Employee::factory()->count(2)->create(['department_id' => $dept2->id]);

        $employees = Employee::byDepartment($dept1)->count();

        $this->assertEquals(3, $employees);
    }

    public function test_employee_can_retrieve_full_name(): void
    {
        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $employee->full_name);
    }

    public function test_employee_age_calculation(): void
    {
        $employee = Employee::factory()->create([
            'date_of_birth' => now()->subYears(30)->toDateString(),
        ]);

        $age = $employee->getAge();

        $this->assertEquals(30, $age);
    }

    public function test_employee_years_of_service_calculation(): void
    {
        $employee = Employee::factory()->create([
            'hire_date' => now()->subYears(5)->toDateString(),
        ]);

        $years = $employee->getYearsOfService();

        $this->assertEquals(5, $years);
    }

    public function test_employee_can_archive(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);

        $employee->archive();

        $this->assertEquals('archived', $employee->refresh()->status);
    }

    public function test_employee_has_leave_balance(): void
    {
        $employee = Employee::factory()->create(['annual_leave_balance' => 20]);

        $this->assertEquals(20, $employee->annual_leave_balance);
    }

    public function test_employee_can_deduct_leave(): void
    {
        $employee = Employee::factory()->create(['annual_leave_balance' => 20]);

        $employee->deductLeave(5);

        $this->assertEquals(15, $employee->refresh()->annual_leave_balance);
    }

    public function test_employee_cannot_deduct_more_leave_than_available(): void
    {
        $employee = Employee::factory()->create(['annual_leave_balance' => 10]);

        $this->expectException(\Exception::class);
        $employee->deductLeave(15);
    }
}
