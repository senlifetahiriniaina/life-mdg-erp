<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Models\DepreciationSchedule;
use Tests\TestCase;

class AccountingEdgeCaseTests extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        // Grant all permissions for edge case testing
        $permissions = [
            'accounting.tax_compliance.view',
            'accounting.tax_compliance.create',
            'accounting.tax_compliance.update',
            'accounting.depreciation.view',
            'accounting.depreciation.create',
            'accounting.depreciation.update',
            'accounting.depreciation.record',
        ];

        foreach ($permissions as $perm) {
            $this->user->givePermissionTo($perm);
        }
    }

    // ============================================================================
    // EDGE CASE: VERY LARGE MONETARY VALUES
    // ============================================================================

    public function test_depreciation_schedule_with_very_high_depreciation_rate(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 1,
                'residual_value' => 0,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 999999999.99,
                'depreciation_expense_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
                'accumulated_depreciation_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
            ]);

        $response->assertCreated();
    }

    // ============================================================================
    // EDGE CASE: NEGATIVE/INVALID VALUES
    // ============================================================================

    public function test_depreciation_schedule_rejects_negative_residual(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 10,
                'residual_value' => -1000,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 10000,
                'depreciation_expense_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
                'accumulated_depreciation_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
            ]);

        $response->assertUnprocessable();
    }

    // ============================================================================
    // EDGE CASE: DATE BOUNDARIES
    // ============================================================================

    public function test_depreciation_schedule_with_very_short_useful_life(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 1,
                'residual_value' => 1000,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 9000,
                'depreciation_expense_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
                'accumulated_depreciation_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
            ]);

        $response->assertCreated();
    }

    public function test_depreciation_schedule_with_very_long_useful_life(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 100,
                'residual_value' => 1000,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 99,
                'depreciation_expense_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
                'accumulated_depreciation_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
            ]);

        $response->assertCreated();
    }

    // ============================================================================
    // EDGE CASE: TAX CALCULATION BOUNDARIES
    // ============================================================================

    public function test_tax_report_with_liability_equals_payment(): void
    {
        $jurisdiction = \Modules\Accounting\Models\TaxJurisdiction::factory()->create();

        $amount = 50000.00;

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/tax-compliance-reports', [
                'tax_jurisdiction_id' => $jurisdiction->id,
                'report_period_start' => '2026-01-01',
                'report_period_end' => '2026-12-31',
                'total_tax_liability' => $amount,
                'total_tax_paid' => $amount,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tax_compliance_reports', [
            'total_tax_liability' => $amount,
            'total_tax_paid' => $amount,
            'tax_due_or_refund' => 0,
        ]);
    }

    public function test_tax_report_with_overpayment(): void
    {
        $jurisdiction = \Modules\Accounting\Models\TaxJurisdiction::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/tax-compliance-reports', [
                'tax_jurisdiction_id' => $jurisdiction->id,
                'report_period_start' => '2026-01-01',
                'report_period_end' => '2026-12-31',
                'total_tax_liability' => 40000.00,
                'total_tax_paid' => 50000.00,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tax_compliance_reports', [
            'total_tax_liability' => 40000.00,
            'total_tax_paid' => 50000.00,
            'tax_due_or_refund' => -10000.00,
        ]);
    }

    // ============================================================================
    // EDGE CASE: DUPLICATE/CONCURRENT OPERATIONS
    // ============================================================================

    public function test_multiple_depreciation_records_same_period(): void
    {
        $schedule = \Modules\Accounting\Models\DepreciationSchedule::factory()
            ->create(['status' => 'active']);

        $periodDate = now()->toDateString();

        // First depreciation entry for period
        $response1 = $this->actingAs($this->user)
            ->postJson("/api/accounting/depreciation-schedules/{$schedule->id}/record", [
                'period_date' => $periodDate,
            ]);

        $response1->assertCreated();

        // Attempt duplicate entry for same period
        $response2 = $this->actingAs($this->user)
            ->postJson("/api/accounting/depreciation-schedules/{$schedule->id}/record", [
                'period_date' => $periodDate,
            ]);

        // Should fail due to unique constraint
        $response2->assertUnprocessable();
    }

    // ============================================================================
    // EDGE CASE: BOUNDARY DECIMAL PRECISION
    // ============================================================================

    public function test_decimal_precision_in_calculations(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()->for($this->company)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 3,
                'residual_value' => 0.01,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 333.33,
                'depreciation_expense_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
                'accumulated_depreciation_account_id' => \Modules\Accounting\Models\GlAccount::factory()->for($this->company)->create()->id,
            ]);

        $response->assertCreated();
        $schedule = \Modules\Accounting\Models\DepreciationSchedule::find($response->json('data.id'));
        $this->assertEquals(333.33, $schedule->annual_depreciation_amount);
    }

    // ============================================================================
    // EDGE CASE: VERY LONG TEXT FIELDS
    // ============================================================================

    public function test_long_description_in_consolidation(): void
    {
        $longDescription = str_repeat('A very long description. ', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/consolidation-hierarchies', [
                'name' => 'Test Hierarchy',
                'description' => $longDescription,
                'type' => 'holding',
                'company_id' => $this->company->id,
                'ownership_percentage' => 100,
                'effective_date' => now()->toDateString(),
            ]);

        $response->assertCreated();
    }
}
