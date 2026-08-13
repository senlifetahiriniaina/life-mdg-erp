<?php

declare(strict_types=1);

namespace Tests\Unit\Services\HR;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Department;
use Modules\HR\Services\EmployeeService;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmployeeService();
    }

    public function test_can_create_employee_with_valid_data(): void
    {
        $department = Department::factory()->create();

        $employee = $this->service->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'department_id' => $department->id,
            'employment_type' => 'full-time',
        ]);

        $this->assertInstanceOf(Employee::class, $employee);
        $this->assertEquals('John', $employee->first_name);
        $this->assertEquals('Doe', $employee->last_name);
        $this->assertEquals('john.doe@example.com', $employee->email);
    }

    public function test_create_employee_validates_required_fields(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->service->create([
            'first_name' => 'John',
            // Missing last_name, email, department_id, employment_type
        ]);
    }

    public function test_cannot_create_employee_with_duplicate_email(): void
    {
        $department = Department::factory()->create();
        Employee::factory()->create(['email' => 'john@example.com']);

        $this->expectException(\Exception::class);
        $this->service->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'department_id' => $department->id,
            'employment_type' => 'full-time',
        ]);
    }

    public function test_can_update_employee(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'John']);

        $updated = $this->service->update($employee, ['first_name' => 'Jane']);

        $this->assertEquals('Jane', $updated->first_name);
        $this->assertEquals('Jane', $employee->refresh()->first_name);
    }

    public function test_can_assign_department_to_employee(): void
    {
        $employee = Employee::factory()->create();
        $department = Department::factory()->create();

        $result = $this->service->assignDepartment($employee, $department);

        $this->assertTrue($result);
        $this->assertEquals($department->id, $employee->refresh()->department_id);
    }

    public function test_can_activate_employee(): void
    {
        $employee = Employee::factory()->create(['status' => 'inactive']);

        $result = $this->service->activate($employee);

        $this->assertTrue($result);
        $this->assertEquals('active', $employee->refresh()->status);
    }

    public function test_can_deactivate_employee(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);

        $result = $this->service->deactivate($employee);

        $this->assertTrue($result);
        $this->assertEquals('inactive', $employee->refresh()->status);
    }

    public function test_can_get_employee_by_email(): void
    {
        $employee = Employee::factory()->create(['email' => 'john@example.com']);

        $found = $this->service->getByEmail('john@example.com');

        $this->assertInstanceOf(Employee::class, $found);
        $this->assertEquals($employee->id, $found->id);
    }

    public function test_get_employee_by_email_returns_null_if_not_found(): void
    {
        $found = $this->service->getByEmail('nonexistent@example.com');

        $this->assertNull($found);
    }

    public function test_can_delete_employee(): void
    {
        $employee = Employee::factory()->create();
        $id = $employee->id;

        $result = $this->service->delete($employee);

        $this->assertTrue($result);
        $this->assertNull(Employee::find($id));
    }

    public function test_cannot_delete_active_employee_without_force(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);

        $this->expectException(\Exception::class);
        $this->service->delete($employee, force: false);
    }

    public function test_can_force_delete_active_employee(): void
    {
        $employee = Employee::factory()->create(['status' => 'active']);
        $id = $employee->id;

        $result = $this->service->delete($employee, force: true);

        $this->assertTrue($result);
        $this->assertNull(Employee::find($id));
    }

    public function test_can_retrieve_active_employees(): void
    {
        Employee::factory()->count(3)->create(['status' => 'active']);
        Employee::factory()->count(2)->create(['status' => 'inactive']);

        $active = $this->service->getActive();

        $this->assertEquals(3, $active->count());
    }

    public function test_can_retrieve_employees_by_department(): void
    {
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        Employee::factory()->count(3)->create(['department_id' => $dept1->id]);
        Employee::factory()->count(2)->create(['department_id' => $dept2->id]);

        $employees = $this->service->getByDepartment($dept1);

        $this->assertEquals(3, $employees->count());
    }
}
