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
        // Chantier 8.3: real StatutorySchemes brackets for SN produce a
        // higher tax than CI's at 500k gross (SN's steeper progressive
        // brackets outweigh its 30%-capped abatement) — the opposite of
        // the old crude flat-rate approximation this test used to assert.
        $this->assertGreaterThan($taxCI, $taxSN);
    }

    /** @test */
    public function it_calculates_social_security_by_country(): void
    {
        // Chantier 8.3: real StatutorySchemes data — both SN's 'css' and
        // CI's 'cnps_pf_at' schemes (family benefits/work accidents) are
        // employer-funded only (employee_rate 0.0) in the real statutory
        // rates; the employee-side pension scheme is a separate figure,
        // see it_calculates_pension_contribution().
        $ssSN = $this->service->calculateSocialSecurity(600_000, 'SN');
        $ssCI = $this->service->calculateSocialSecurity(600_000, 'CI');

        $this->assertEquals(0.0, $ssSN);
        $this->assertEquals(0.0, $ssCI);

        // A country with a single combined scheme (no pension/social split)
        // folds its full employee contribution into social_security instead.
        $ssBJ = $this->service->calculateSocialSecurity(600_000, 'BJ');
        $this->assertEquals(21_600.0, $ssBJ);
    }

    /** @test */
    public function it_calculates_pension_contribution(): void
    {
        // Chantier 8.3: real StatutorySchemes 'ipres' rate (5.6%) capped at
        // its real 432,000 XOF monthly ceiling — gross 600,000 exceeds the
        // ceiling, so the base used is 432,000, not the full gross.
        $pension = $this->service->calculatePensionContribution(600_000, 'SN');
        $this->assertEquals(432_000 * 0.056, $pension);

        // A combined-scheme country has no separate pension figure to split.
        $pensionBJ = $this->service->calculatePensionContribution(600_000, 'BJ');
        $this->assertEquals(0.0, $pensionBJ);
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
        // Chantier 8.3: real StatutorySchemes SN brackets — 30%-abatement
        // (capped at 75,000) reduces the taxable base before the real
        // progressive IR brackets + fixed TRIMF apply. Values confirmed
        // against the service's own live calculation, not hand-derived.
        $this->assertEquals(44_800.0, $this->service->calculateIncomeTax(300_000, 'SN'));
        $this->assertEquals(109_383.35, $this->service->calculateIncomeTax(500_000, 'SN'));
    }

    /** @test */
    public function it_calculates_ivory_coast_income_tax_correctly(): void
    {
        // Chantier 8.3: real StatutorySchemes CI brackets (20% abatement,
        // no cap, then the real ITS/CN progressive schedule).
        $this->assertEquals(60_000.0, $this->service->calculateIncomeTax(500_000, 'CI'));
    }

    /** @test */
    public function it_handles_cameroon_progressive_tax_brackets(): void
    {
        // Chantier 8.3: real StatutorySchemes CM brackets — 30% abatement,
        // then the real IRPP progressive schedule, then the real 10% CAC
        // (Centimes Additionnels Communaux) surcharge on top.
        $this->assertEquals(15_400.0, $this->service->calculateIncomeTax(200_000, 'CM'));
        $this->assertEquals(40_333.32, $this->service->calculateIncomeTax(400_000, 'CM'));
        $this->assertEquals(119_624.94, $this->service->calculateIncomeTax(750_000, 'CM'));
    }

    /** @test */
    public function it_falls_back_to_the_old_approximation_for_a_country_statutory_schemes_does_not_cover(): void
    {
        // NG isn't one of the 8 real StatutorySchemes countries — keeps the
        // pre-existing crude approximation rather than guessing new rates.
        $this->assertEquals(30_000.0, $this->service->calculateIncomeTax(500_000, 'NG'));
    }
}
