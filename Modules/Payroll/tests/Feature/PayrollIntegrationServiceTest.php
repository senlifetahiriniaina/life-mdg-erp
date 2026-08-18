<?php

declare(strict_types=1);

namespace Modules\Payroll\Tests\Feature;

use Tests\TestCase;
use Modules\Payroll\Services\PayrollIntegrationService;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;
use Modules\Payroll\Models\Payslip;
use App\Models\User;
use Carbon\Carbon;

class PayrollIntegrationServiceTest extends TestCase
{
    private PayrollIntegrationService $service;
    private Employee $employee;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayrollIntegrationService::class);

        // Chantier 8.3: base_salary/salary_currency/country_code/tenant_id
        // are real hr_employees columns but not in Employee's $fillable —
        // the factory's unguarded() bypass could set them directly, but that
        // exercised a code path real production writes (EmployeeController,
        // EmployeeManagementService) can never reach. The real salary source
        // is EmployeeCompensation, and the real tenant source is the linked
        // User's tenant_id — set up both to match how the fixed service
        // actually resolves them.
        $this->user = User::factory()->create(['tenant_id' => 1]);
        $this->employee = Employee::factory()->create([
            'user_id'          => $this->user->id,
            'status'           => 'active',
            'termination_date' => null,
        ]);
        EmployeeCompensation::factory()->create([
            'employee_id'    => $this->employee->id,
            'base_salary'    => 500_000,
            'currency'       => 'XOF',
            'effective_date' => now()->startOfMonth()->subMonth(),
            'end_date'       => null,
        ]);
    }

    /** @test */
    public function it_calculates_salary_components(): void
    {
        $components = $this->service->calculateSalaryComponents(
            $this->employee,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertIsArray($components);
        $this->assertArrayHasKey('base_salary', $components);
        $this->assertArrayHasKey('allowances', $components);
        $this->assertArrayHasKey('gross_salary', $components);
        $this->assertEquals(500_000.0, $components['base_salary']);
        $this->assertGreaterThanOrEqual($components['base_salary'], $components['gross_salary']);
    }

    /** @test */
    public function it_calculates_deductions(): void
    {
        $deductions = $this->service->calculateDeductions($this->employee, 600_000);

        $this->assertIsArray($deductions);
        $this->assertArrayHasKey('deductions', $deductions);
        $this->assertArrayHasKey('total_deductions', $deductions);
        $this->assertGreaterThan(0, $deductions['total_deductions']);
    }

    /** @test */
    public function it_calculates_income_tax_by_country(): void
    {
        $taxSN = $this->service->calculateIncomeTax(500_000, 'SN');
        $taxCI = $this->service->calculateIncomeTax(500_000, 'CI');
        $taxNG = $this->service->calculateIncomeTax(500_000, 'NG');

        $this->assertIsFloat($taxSN);
        $this->assertIsFloat($taxCI);
        $this->assertIsFloat($taxNG);
        // CI flat rate is higher than SN threshold-based rate on 500k
        $this->assertGreaterThan($taxSN, $taxCI);
    }

    /** @test */
    public function it_calculates_social_security_by_country(): void
    {
        $ssSN = $this->service->calculateSocialSecurity(600_000, 'SN');
        $ssCI = $this->service->calculateSocialSecurity(600_000, 'CI');

        $this->assertEquals(600_000 * 0.055, $ssSN);
        $this->assertEquals(600_000 * 0.065, $ssCI);
    }

    /** @test */
    public function it_calculates_pension_contribution(): void
    {
        $pension = $this->service->calculatePensionContribution(600_000, 'SN');
        $this->assertEquals(600_000 * 0.05, $pension);
    }

    /** @test */
    public function it_generates_payslip(): void
    {
        $record = $this->service->generatePayslip(
            $this->employee,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertNotNull($record);
        $this->assertInstanceOf(Payslip::class, $record);
        $this->assertGreaterThan(0, $record->gross_salary);
        $this->assertLessThan((float) $record->gross_salary + 1, (float) $record->net_salary);
        $this->assertEquals('draft', $record->status);
    }

    /** @test */
    public function it_prevents_duplicate_payslips(): void
    {
        $start = now()->startOfMonth();
        $end   = now()->endOfMonth();

        $r1 = $this->service->generatePayslip($this->employee, $start, $end);
        $r2 = $this->service->generatePayslip($this->employee, $start, $end);

        $this->assertEquals($r1->id, $r2->id);
    }

    /** @test */
    public function it_generates_payslips_for_all_active_employees(): void
    {
        $secondEmployee = Employee::factory()->create(['status' => 'active']);
        EmployeeCompensation::factory()->create([
            'employee_id'    => $secondEmployee->id,
            'base_salary'    => 400_000,
            'currency'       => 'XOF',
            'effective_date' => now()->startOfMonth()->subMonth(),
            'end_date'       => null,
        ]);

        $result = $this->service->generatePayslips(
            $this->user->tenant_id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('created_count', $result);
        $this->assertGreaterThanOrEqual(2, $result['created_count']);
    }

    /** @test */
    public function it_skips_inactive_employees(): void
    {
        $this->employee->update(['status' => 'inactive']);

        $result = $this->service->generatePayslips(
            $this->user->tenant_id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertEquals(0, $result['created_count']);
    }

    /** @test */
    public function it_gets_payroll_summary(): void
    {
        $this->service->generatePayslip(
            $this->employee,
            now()->startOfMonth(),
            now()->endOfMonth(),
            'monthly',
            $this->user->tenant_id
        );

        $summary = $this->service->getPayrollSummary(
            $this->user->tenant_id,
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('employee_count', $summary);
        $this->assertArrayHasKey('total_gross', $summary);
        $this->assertArrayHasKey('total_net', $summary);
        $this->assertGreaterThan(0, $summary['total_gross']);
    }

    /** @test */
    public function it_calculates_senegal_income_tax_correctly(): void
    {
        // Below threshold — no tax
        $this->assertEquals(0.0, $this->service->calculateIncomeTax(300_000, 'SN'));

        // Above threshold — 20% on excess
        $expected = (500_000 - 400_000) * 0.20;
        $this->assertEquals($expected, $this->service->calculateIncomeTax(500_000, 'SN'));
    }

    /** @test */
    public function it_calculates_ivory_coast_income_tax_correctly(): void
    {
        $this->assertEquals(500_000 * 0.18, $this->service->calculateIncomeTax(500_000, 'CI'));
    }

    /** @test */
    public function it_handles_cameroon_progressive_tax_brackets(): void
    {
        $this->assertEquals(0.0, $this->service->calculateIncomeTax(200_000, 'CM'));

        $expected = (400_000 - 250_000) * 0.10;
        $this->assertEquals($expected, $this->service->calculateIncomeTax(400_000, 'CM'));

        $expected = 25_000 + ((750_000 - 500_000) * 0.15);
        $this->assertEquals($expected, $this->service->calculateIncomeTax(750_000, 'CM'));
    }
}
