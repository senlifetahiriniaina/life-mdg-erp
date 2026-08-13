<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\RevenueContract;
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
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        // Grant all permissions for edge case testing
        $permissions = [
            'accounting.revenue_recognition.view',
            'accounting.revenue_recognition.create',
            'accounting.revenue_recognition.update',
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

    public function test_revenue_contract_with_maximum_contract_value(): void
    {
        $maxValue = 999999999.99;

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-LARGE-001',
                'contract_type' => 'Service',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => $maxValue,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('revenue_contracts', [
            'contract_value' => $maxValue,
        ]);
    }

    public function test_revenue_contract_with_zero_value(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-ZERO-001',
                'contract_type' => 'Service',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 0,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertCreated();
    }

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

    public function test_revenue_contract_rejects_negative_value(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-NEG-001',
                'contract_type' => 'Service',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => -1000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertUnprocessable();
    }

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

    public function test_revenue_contract_with_same_date_for_start_and_end(): void
    {
        $sameDate = now()->toDateString();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-SAMEDATE-001',
                'contract_type' => 'Service',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => $sameDate,
                'start_date' => $sameDate,
                'end_date' => $sameDate,
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'point_in_time',
            ]);

        $response->assertCreated();
    }

    public function test_revenue_contract_end_date_before_start_date_rejected(): void
    {
        $startDate = now()->toDateString();
        $endDate = now()->subMonths(1)->toDateString();

        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-BADDATE-001',
                'contract_type' => 'Service',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => $startDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertUnprocessable();
    }

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

    public function test_duplicate_contract_number_rejected(): void
    {
        $contractNumber = 'CONTRACT-UNIQUE-001';
        $customerId = \App\Models\Customer::factory()->for($this->company)->create()->id;

        // First contract
        $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => $contractNumber,
                'contract_type' => 'Service',
                'customer_id' => $customerId,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        // Duplicate attempt
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => $contractNumber,
                'contract_type' => 'Service',
                'customer_id' => $customerId,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.contract_number.0', 'The contract number has already been taken.');
    }

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
    // EDGE CASE: SPECIAL CHARACTERS IN TEXT FIELDS
    // ============================================================================

    public function test_contract_with_special_characters_in_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-SPECIAL-001',
                'contract_type' => 'Service & Consulting (Premium)',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('revenue_contracts', [
            'contract_type' => 'Service & Consulting (Premium)',
        ]);
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
    // EDGE CASE: EMPTY/NULL OPTIONAL FIELDS
    // ============================================================================

    public function test_revenue_contract_without_optional_end_date(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/accounting/revenue-contracts', [
                'contract_number' => 'CONTRACT-NOEND-001',
                'contract_type' => 'Perpetual License',
                'customer_id' => \App\Models\Customer::factory()->for($this->company)->create()->id,
                'contract_date' => now()->toDateString(),
                'start_date' => now()->toDateString(),
                'contract_value' => 10000,
                'currency' => 'USD',
                'performance_obligation_type' => 'time_based',
                'revenue_recognition_method' => 'over_time',
                // end_date intentionally omitted
            ]);

        $response->assertCreated();
        $contract = RevenueContract::find($response->json('data.id'));
        $this->assertNull($contract->end_date);
    }

    // ============================================================================
    // EDGE CASE: COMPANY ISOLATION
    // ============================================================================

    public function test_cannot_access_other_company_contracts(): void
    {
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->for($otherCompany)->create();

        $contract = RevenueContract::factory()
            ->for(\App\Models\Customer::factory()->for($otherCompany)->create())
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/accounting/revenue-contracts/{$contract->id}");

        $response->assertForbidden();
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

    // ============================================================================
    // EDGE CASE: MULTIPLE CURRENCIES
    // ============================================================================

    public function test_revenue_contracts_in_different_currencies(): void
    {
        $customerId = \App\Models\Customer::factory()->for($this->company)->create()->id;
        $currencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD'];

        foreach ($currencies as $currency) {
            $response = $this->actingAs($this->user)
                ->postJson('/api/accounting/revenue-contracts', [
                    'contract_number' => "CONTRACT-{$currency}-001",
                    'contract_type' => 'Service',
                    'customer_id' => $customerId,
                    'contract_date' => now()->toDateString(),
                    'start_date' => now()->toDateString(),
                    'contract_value' => 10000,
                    'currency' => $currency,
                    'performance_obligation_type' => 'time_based',
                    'revenue_recognition_method' => 'over_time',
                ]);

            $response->assertCreated();
        }

        $this->assertDatabaseCount('revenue_contracts', 5);
    }
}
