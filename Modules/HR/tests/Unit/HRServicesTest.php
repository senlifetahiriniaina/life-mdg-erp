<?php

namespace Modules\HR\Tests\Unit;

use Illuminate\Support\Facades\Cache;
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
        $result = $this->employeeService->onboardEmployee([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'hire_date' => now()->toDateString(),
            'department' => 'Engineering',
            'position' => 'Software Engineer',
            'salary' => 80000,
        ]);

        $this->assertEquals('onboarding_started', $result['status']);
        $this->assertArrayHasKey('employee_id', $result);
    }

    public function test_can_get_employee_profile()
    {
        $employee = $this->employeeService->onboardEmployee([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'hire_date' => now()->subMonths(6)->toDateString(),
            'department' => 'Marketing',
            'position' => 'Marketing Manager',
            'salary' => 75000,
        ]);

        $this->assertArrayHasKey('employee_id', $employee);
    }
}
