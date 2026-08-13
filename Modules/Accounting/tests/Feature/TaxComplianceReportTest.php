<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Models\TaxJurisdiction;
use Tests\TestCase;

class TaxComplianceReportTest extends TestCase
{
    protected User $user;
    protected Company $company;
    protected TaxJurisdiction $jurisdiction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();
        $this->jurisdiction = TaxJurisdiction::factory()->create();

        $this->user->givePermissionTo('accounting.tax_compliance.view');
        $this->user->givePermissionTo('accounting.tax_compliance.create');
        $this->user->givePermissionTo('accounting.tax_compliance.update');
        $this->user->givePermissionTo('accounting.tax_compliance.file');
    }

    public function test_can_create_tax_report(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/tax-compliance-reports', [
                'tax_jurisdiction_id' => $this->jurisdiction->id,
                'report_period_start' => '2026-01-01',
                'report_period_end' => '2026-12-31',
                'total_tax_liability' => 50000.00,
                'total_tax_paid' => 45000.00,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tax_compliance_reports', [
            'company_id' => $this->company->id,
            'total_tax_liability' => 50000.00,
        ]);
    }

    public function test_can_list_tax_reports(): void
    {
        TaxComplianceReport::factory()
            ->for($this->company)
            ->for($this->jurisdiction)
            ->count(3)
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/accounting/tax-compliance-reports');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_file_tax_report(): void
    {
        $report = TaxComplianceReport::factory()
            ->for($this->company)
            ->for($this->jurisdiction)
            ->create(['status' => 'reviewed']);

        $this->user->givePermissionTo('accounting.tax_compliance.file');

        $response = $this->actingAs($this->user)
            ->postJson("/api/accounting/tax-compliance-reports/{$report->id}/file", [
                'filing_reference_number' => 'IRS-2026-12345',
            ]);

        $response->assertOk();
        $report->refresh();
        $this->assertEquals('filed', $report->status);
        $this->assertEquals('IRS-2026-12345', $report->filing_reference_number);
    }

    public function test_cannot_file_draft_report(): void
    {
        $report = TaxComplianceReport::factory()
            ->for($this->company)
            ->for($this->jurisdiction)
            ->create(['status' => 'draft']);

        $this->user->givePermissionTo('accounting.tax_compliance.file');

        $response = $this->actingAs($this->user)
            ->postJson("/api/accounting/tax-compliance-reports/{$report->id}/file", [
                'filing_reference_number' => 'IRS-2026-12345',
            ]);

        $response->assertForbidden();
    }

    public function test_calculates_tax_due_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/tax-compliance-reports', [
                'tax_jurisdiction_id' => $this->jurisdiction->id,
                'report_period_start' => '2026-01-01',
                'report_period_end' => '2026-12-31',
                'total_tax_liability' => 60000.00,
                'total_tax_paid' => 45000.00,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tax_compliance_reports', [
            'total_tax_liability' => 60000.00,
            'total_tax_paid' => 45000.00,
            'tax_due_or_refund' => 15000.00,
        ]);
    }
}
