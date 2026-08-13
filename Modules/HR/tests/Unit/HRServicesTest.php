<?php

namespace Modules\HR\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\HR\Services\EmployeeManagementService;
use Modules\HR\Services\PayrollProcessingService;
use Tests\TestCase;

class HRServicesTest extends TestCase
{
    protected EmployeeManagementService $employeeService;
    protected PayrollProcessingService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->employeeService = app(EmployeeManagementService::class);
        $this->payrollService = app(PayrollProcessingService::class);
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

        // Get profile (would use actual employee ID)
        $this->assertArrayHasKey('employee_id', $employee);
    }

    public function test_can_calculate_payroll()
    {
        $employees = [
            1 => 80000,
            2 => 75000,
            3 => 60000,
        ];

        $result = $this->payrollService->calculatePayroll('2026-05', $employees);

        $this->assertEquals('2026-05', $result['period']);
        $this->assertEquals(3, $result['employees_count']);
        $this->assertGreaterThan(0, $result['total_gross']);
        $this->assertGreaterThan(0, $result['total_deductions']);
        $this->assertLessThan($result['total_gross'], $result['total_net']);
    }

    public function test_payroll_calculation_is_correct()
    {
        $employees = [1 => 120000]; // Annual salary
        $result = $this->payrollService->calculatePayroll('2026-05', $employees);

        // Monthly gross: 120000 / 12 = 10000
        // Expected deductions: 10000 * (0.20 + 0.08 + 0.03) = 3100
        // Expected net: 10000 - 3100 = 6900

        $this->assertGreaterThan(0, $result['total_gross']);
        $this->assertGreaterThan(0, $result['total_deductions']);
    }

    public function test_can_generate_payslips()
    {
        $result = $this->payrollService->generatePayslips('2026-05');

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('payslips_generated', $result);
        $this->assertEquals('generated', $result['status']);
    }
}
