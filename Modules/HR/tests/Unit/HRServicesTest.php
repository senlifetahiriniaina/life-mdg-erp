<?php

namespace Modules\HR\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\HR\Models\Department;
use Modules\HR\Models\JobPosition;
use Modules\HR\Services\EmployeeManagementService;
use Tests\TestCase;

/**
 * Payroll tests that used to live here were removed with HR's legacy
 * PayrollProcessingService (intentionally deleted during the extraction —
 * per CLAUDE.md, the dedicated Payroll module is the single source of
 * truth for payroll; its own test suite covers that scope).
 */
class HRServicesTest extends TestCase
{
    protected EmployeeManagementService $employeeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employeeService = app(EmployeeManagementService::class);
        Cache::flush();
    }

    public function test_can_onboard_employee()
    {
        // Chantier 8.3: the real Employee model has no department/position/
        // salary columns (department_id/job_position_id FKs instead; salary
        // lives on EmployeeCompensation) — these plain string values were
        // silently dropped by mass assignment before the service was fixed
        // to use the real FK columns.
        $department = Department::factory()->create();
        $position = JobPosition::factory()->create(['department_id' => $department->id]);

        $result = $this->employeeService->onboardEmployee([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'hire_date' => now()->toDateString(),
            'department_id' => $department->id,
            'job_position_id' => $position->id,
        ]);

        $this->assertEquals('onboarding_started', $result['status']);
        $this->assertArrayHasKey('employee_id', $result);
    }

    public function test_can_get_employee_profile()
    {
        $department = Department::factory()->create();
        $position = JobPosition::factory()->create(['department_id' => $department->id]);

        $employee = $this->employeeService->onboardEmployee([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'hire_date' => now()->subMonths(6)->toDateString(),
            'department_id' => $department->id,
            'job_position_id' => $position->id,
        ]);

        $this->assertArrayHasKey('employee_id', $employee);
    }
}
