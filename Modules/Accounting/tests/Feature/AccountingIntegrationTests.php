<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Modules\Accounting\Models\RevenueRecognitionSchedule;
use Modules\Accounting\Models\DepreciationEntry;
use Modules\Accounting\Models\IntercompanyReconciliation;
use Tests\TestCase;

class AccountingIntegrationTests extends TestCase
{
    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->for($this->company)->create();

        $permissions = [
            'accounting.revenue_recognition.view',
            'accounting.revenue_recognition.create',
            'accounting.revenue_recognition.recognize',
            'accounting.tax_compliance.view',
            'accounting.tax_compliance.create',
            'accounting.consolidation.view',
            'accounting.consolidation.create',
            'accounting.intercompany.view',
            'accounting.intercompany.create',
            'accounting.depreciation.view',
            'accounting.depreciation.create',
            'accounting.depreciation.record',
        ];

        foreach ($permissions as $perm) {
            $this->user->givePermissionTo($perm);
        }
    }

    // ============================================================================
    // INTEGRATION: Revenue Recognition → Journal Entries
    // ============================================================================

    public function test_revenue_recognition_with_journal_entry_linkage(): void
    {
        $contract = \Modules\Accounting\Models\RevenueContract::factory()
            ->for(\App\Models\Customer::factory()->for($this->company)->create())
            ->create(['status' => 'active']);

        $journalEntry = \Modules\Accounting\Models\JournalEntry::factory()
            ->for($this->company)
            ->create();

        $glAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($this->company)
            ->create();

        $schedule = RevenueRecognitionSchedule::create([
            'revenue_contract_id' => $contract->id,
            'recognition_date' => now()->toDateString(),
            'amount' => 5000,
            'tax_amount' => 500,
            'gl_account_id' => $glAccount->id,
            'journal_entry_id' => $journalEntry->id,
            'status' => 'recognized',
            'recognized_at' => now(),
        ]);

        $this->assertNotNull($schedule->journal_entry_id);
        $this->assertTrue($schedule->journalEntry()->exists());
        $this->assertEquals($journalEntry->id, $schedule->journal_entry_id);
    }

    public function test_revenue_recognition_without_journal_entry(): void
    {
        $contract = \Modules\Accounting\Models\RevenueContract::factory()
            ->for(\App\Models\Customer::factory()->for($this->company)->create())
            ->create(['status' => 'active']);

        $glAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($this->company)
            ->create();

        $schedule = RevenueRecognitionSchedule::create([
            'revenue_contract_id' => $contract->id,
            'recognition_date' => now()->toDateString(),
            'amount' => 5000,
            'gl_account_id' => $glAccount->id,
            'status' => 'scheduled',
            'journal_entry_id' => null,
        ]);

        $this->assertNull($schedule->journal_entry_id);
        $this->assertFalse($schedule->journalEntry()->exists());
    }

    // ============================================================================
    // INTEGRATION: Depreciation → Journal Entries & GL Accounts
    // ============================================================================

    public function test_depreciation_entry_creates_gl_account_references(): void
    {
        $asset = \Modules\Accounting\Models\FixedAsset::factory()
            ->for($this->company)
            ->create();

        $expenseAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($this->company)
            ->create(['account_type' => 'Expense']);

        $accumulatedAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($this->company)
            ->create(['account_type' => 'Asset']);

        $schedule = \Modules\Accounting\Models\DepreciationSchedule::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 10,
            'residual_value' => 5000,
            'depreciation_start_date' => now()->toDateString(),
            'annual_depreciation_amount' => 9500,
            'accumulated_depreciation' => 0,
            'book_value' => 100000,
            'depreciation_expense_account_id' => $expenseAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedAccount->id,
            'status' => 'active',
        ]);

        $this->assertTrue($schedule->depreciationExpenseAccount()->exists());
        $this->assertTrue($schedule->accumulatedDepreciationAccount()->exists());
        $this->assertEquals($expenseAccount->id, $schedule->depreciation_expense_account_id);
        $this->assertEquals($accumulatedAccount->id, $schedule->accumulated_depreciation_account_id);
    }

    public function test_depreciation_entry_linked_to_journal_entry(): void
    {
        $schedule = \Modules\Accounting\Models\DepreciationSchedule::factory()
            ->create();

        $journalEntry = \Modules\Accounting\Models\JournalEntry::factory()
            ->for($schedule->fixedAsset->company)
            ->create();

        $entry = DepreciationEntry::create([
            'depreciation_schedule_id' => $schedule->id,
            'period_date' => now()->toDateString(),
            'depreciation_amount' => 791.67,
            'accumulated_depreciation' => 791.67,
            'book_value' => 99208.33,
            'journal_entry_id' => $journalEntry->id,
            'status' => 'recorded',
            'recorded_at' => now(),
        ]);

        $this->assertNotNull($entry->journal_entry_id);
        $this->assertTrue($entry->journalEntry()->exists());
    }

    // ============================================================================
    // INTEGRATION: Consolidation → GL Accounts & Companies
    // ============================================================================

    public function test_consolidation_entries_reference_correct_accounts(): void
    {
        $parentCompany = Company::factory()->create();
        $subsidiary = Company::factory()->create();

        $hierarchy = \Modules\Accounting\Models\ConsolidationHierarchy::create([
            'name' => 'Test Group',
            'type' => 'holding',
            'parent_company_id' => $parentCompany->id,
            'company_id' => $subsidiary->id,
            'ownership_percentage' => 100,
            'effective_date' => now()->toDateString(),
        ]);

        $period = \Modules\Accounting\Models\ConsolidationPeriod::create([
            'consolidation_hierarchy_id' => $hierarchy->id,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'frequency' => 'quarterly',
        ]);

        $glAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($subsidiary)
            ->create();

        $entry = \Modules\Accounting\Models\ConsolidationEntry::create([
            'consolidation_period_id' => $period->id,
            'company_id' => $subsidiary->id,
            'gl_account_id' => $glAccount->id,
            'opening_balance' => 100000,
            'debit_amount' => 50000,
            'credit_amount' => 0,
            'closing_balance' => 150000,
        ]);

        $this->assertTrue($entry->glAccount()->exists());
        $this->assertTrue($entry->company()->exists());
        $this->assertEquals($glAccount->id, $entry->gl_account_id);
        $this->assertEquals($subsidiary->id, $entry->company_id);
    }

    // ============================================================================
    // INTEGRATION: Intercompany → GL Accounts & Companies
    // ============================================================================

    public function test_intercompany_clearance_with_both_companies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $glAccountA = \Modules\Accounting\Models\GlAccount::factory()
            ->for($companyA)
            ->create();

        $glAccountB = \Modules\Accounting\Models\GlAccount::factory()
            ->for($companyB)
            ->create();

        $clearance = \Modules\Accounting\Models\IntercompanyClearance::create([
            'sending_company_id' => $companyA->id,
            'receiving_company_id' => $companyB->id,
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'Sale',
            'amount' => 50000,
            'currency' => 'USD',
            'sending_gl_account_id' => $glAccountA->id,
            'receiving_gl_account_id' => $glAccountB->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($clearance->sendingCompany()->exists());
        $this->assertTrue($clearance->receivingCompany()->exists());
        $this->assertTrue($clearance->sendingGlAccount()->exists());
        $this->assertTrue($clearance->receivingGlAccount()->exists());
        $this->assertEquals($companyA->id, $clearance->sending_company_id);
        $this->assertEquals($companyB->id, $clearance->receiving_company_id);
    }

    public function test_intercompany_reconciliation_detects_discrepancies(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $reconciliation = IntercompanyReconciliation::create([
            'company_a_id' => $companyA->id,
            'company_b_id' => $companyB->id,
            'reconciliation_date' => now()->toDateString(),
            'company_a_balance' => 100000,
            'company_b_balance' => 95000,
            'difference' => 5000,
            'status' => 'exception',
        ]);

        $this->assertEquals(5000, $reconciliation->difference);
        $this->assertEquals('exception', $reconciliation->status);
    }

    // ============================================================================
    // INTEGRATION: Tax Compliance → GL Accounts & Journal Entries
    // ============================================================================

    public function test_tax_jurisdiction_with_filing_template(): void
    {
        $jurisdiction = \Modules\Accounting\Models\TaxJurisdiction::create([
            'jurisdiction_code' => 'US-CA',
            'jurisdiction_name' => 'California',
            'country_code' => 'US',
            'tax_type' => 'Sales Tax',
            'tax_rate' => 7.25,
            'effective_from' => now()->toDateString(),
        ]);

        $template = \Modules\Accounting\Models\TaxFilingTemplate::create([
            'tax_jurisdiction_id' => $jurisdiction->id,
            'filing_form_number' => 'FORM-100',
            'filing_type' => 'Monthly',
            'field_mappings' => json_encode([
                'line_1' => 'gl_account_100',
                'line_2' => 'gl_account_200',
            ]),
        ]);

        $this->assertTrue($template->jurisdiction()->exists());
        $this->assertEquals($jurisdiction->id, $template->tax_jurisdiction_id);
    }

    // ============================================================================
    // INTEGRATION: Contract Liability → GL Accounts
    // ============================================================================

    public function test_contract_liability_references_correct_gl_account(): void
    {
        $contract = \Modules\Accounting\Models\RevenueContract::factory()
            ->for(\App\Models\Customer::factory()->for($this->company)->create())
            ->create();

        $deferredRevenueAccount = \Modules\Accounting\Models\GlAccount::factory()
            ->for($this->company)
            ->create(['account_type' => 'Liability']);

        $liability = \Modules\Accounting\Models\ContractLiability::create([
            'revenue_contract_id' => $contract->id,
            'liability_amount' => 100000,
            'recognized_amount' => 25000,
            'remaining_amount' => 75000,
            'deferred_revenue_account_id' => $deferredRevenueAccount->id,
        ]);

        $this->assertTrue($liability->deferredRevenueAccount()->exists());
        $this->assertEquals($deferredRevenueAccount->id, $liability->deferred_revenue_account_id);
    }

    // ============================================================================
    // INTEGRATION: Multiple Entities in Consolidation Chain
    // ============================================================================

    public function test_consolidation_multi_level_hierarchy(): void
    {
        $holdingCompany = Company::factory()->create();
        $parentSubsidiary = Company::factory()->create();
        $childSubsidiary = Company::factory()->create();

        // Level 1: Holding → Parent Subsidiary
        $hierarchy1 = \Modules\Accounting\Models\ConsolidationHierarchy::create([
            'name' => 'Holding → Parent',
            'type' => 'holding',
            'parent_company_id' => $holdingCompany->id,
            'company_id' => $parentSubsidiary->id,
            'ownership_percentage' => 100,
            'effective_date' => now()->toDateString(),
        ]);

        // Level 2: Parent Subsidiary → Child Subsidiary
        $hierarchy2 = \Modules\Accounting\Models\ConsolidationHierarchy::create([
            'name' => 'Parent → Child',
            'type' => 'subsidiary',
            'parent_company_id' => $parentSubsidiary->id,
            'company_id' => $childSubsidiary->id,
            'ownership_percentage' => 100,
            'effective_date' => now()->toDateString(),
        ]);

        $this->assertTrue($hierarchy1->parentCompany()->exists());
        $this->assertTrue($hierarchy2->parentCompany()->exists());
        $this->assertEquals($holdingCompany->id, $hierarchy1->parent_company_id);
        $this->assertEquals($parentSubsidiary->id, $hierarchy2->parent_company_id);
    }

    // ============================================================================
    // INTEGRATION: Depreciation Policy → Fixed Assets
    // ============================================================================

    public function test_depreciation_policy_applied_to_asset_category(): void
    {
        $policy = \Modules\Accounting\Models\DepreciationPolicy::create([
            'company_id' => $this->company->id,
            'policy_name' => 'Manufacturing Equipment',
            'asset_category' => 'Equipment',
            'depreciation_method' => 'straight_line',
            'default_useful_life_years' => 10,
            'default_residual_percentage' => 5,
            'effective_from' => now()->toDateString(),
        ]);

        $this->assertTrue($policy->company()->exists());
        $this->assertEquals('Equipment', $policy->asset_category);
        $this->assertEquals(10, $policy->default_useful_life_years);
    }
}
