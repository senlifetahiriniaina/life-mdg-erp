<?php

namespace Modules\Accounting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Services\ConsolidationService;

class ConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $parentCompany;
    protected Company $subsidiary1;
    protected Company $subsidiary2;
    protected ConsolidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->parentCompany = Company::factory()->create(['name' => 'Parent Corp']);
        $this->subsidiary1 = Company::factory()->create(['name' => 'Subsidiary 1']);
        $this->subsidiary2 = Company::factory()->create(['name' => 'Subsidiary 2']);

        $this->service = app(ConsolidationService::class);
        $this->actingAs($this->user);
    }

    public function test_can_create_consolidation_group()
    {
        $data = [
            'name' => 'Q1 Consolidation',
            'description' => 'Q1 2026 consolidation',
            'parent_company_id' => $this->parentCompany->id,
            'consolidation_method' => 'full',
            'fiscal_year' => 2026,
            'consolidation_date' => '2026-03-31',
            'created_by' => $this->user->id,
        ];

        $group = $this->service->createConsolidationGroup($data);

        $this->assertDatabaseHas('acc_consolidation_groups', [
            'name' => 'Q1 Consolidation',
            'parent_company_id' => $this->parentCompany->id,
        ]);

        $this->assertEquals('draft', $group->status);
    }

    public function test_can_add_subsidiary_member()
    {
        $group = ConsolidationGroup::factory()->create([
            'parent_company_id' => $this->parentCompany->id,
            'created_by' => $this->user->id,
        ]);

        $memberData = [
            'subsidiary_company_id' => $this->subsidiary1->id,
            'ownership_percentage' => 100,
            'relationship_type' => 'subsidiary',
            'acquisition_date' => '2020-01-01',
            'acquisition_price' => 1000000,
            'exchange_rate' => 1.0,
        ];

        $this->service->addMember($group, $memberData);

        $this->assertDatabaseHas('acc_consolidation_members', [
            'consolidation_group_id' => $group->id,
            'subsidiary_company_id' => $this->subsidiary1->id,
            'ownership_percentage' => 100,
        ]);
    }

    public function test_can_record_intercompany_transaction()
    {
        $group = ConsolidationGroup::factory()->create(['parent_company_id' => $this->parentCompany->id]);

        $transactionData = [
            'from_company_id' => $this->parentCompany->id,
            'to_company_id' => $this->subsidiary1->id,
            'transaction_type' => 'sales',
            'transaction_date' => '2026-03-15',
            'reference_number' => 'INV-001',
            'amount' => 50000,
            'currency' => 'USD',
            'exchange_rate' => 1.0,
        ];

        $transaction = $this->service->recordIntercompanyTransaction($group, $transactionData);

        $this->assertDatabaseHas('acc_intercompany_transactions', [
            'consolidation_group_id' => $group->id,
            'reference_number' => 'INV-001',
            'amount' => 50000,
        ]);

        $this->assertFalse($transaction->is_eliminated);
    }

    public function test_can_eliminate_intercompany_transactions()
    {
        $group = ConsolidationGroup::factory()->create([
            'parent_company_id' => $this->parentCompany->id,
            'auto_eliminate_intercompany' => true,
        ]);

        $this->service->recordIntercompanyTransaction($group, [
            'from_company_id' => $this->parentCompany->id,
            'to_company_id' => $this->subsidiary1->id,
            'transaction_type' => 'sales',
            'transaction_date' => '2026-03-15',
            'reference_number' => 'INV-001',
            'amount' => 50000,
            'currency' => 'USD',
        ]);

        $eliminatedCount = $this->service->eliminateIntercompanyTransactions($group);

        $this->assertEquals(1, $eliminatedCount);

        $transaction = $group->intercompanyTransactions()->first();
        $this->assertTrue($transaction->is_eliminated);

        $this->assertDatabaseHas('acc_consolidation_entries', [
            'consolidation_group_id' => $group->id,
            'entry_type' => 'intercompany_elimination',
        ]);
    }

    public function test_can_generate_consolidated_report()
    {
        $group = ConsolidationGroup::factory()->create(['parent_company_id' => $this->parentCompany->id]);

        $this->service->addMember($group, [
            'subsidiary_company_id' => $this->subsidiary1->id,
            'ownership_percentage' => 100,
            'relationship_type' => 'subsidiary',
            'acquisition_date' => '2020-01-01',
            'acquisition_price' => 1000000,
        ]);

        $report = $this->service->generateConsolidatedReport($group, 'consolidated_balance_sheet');

        $this->assertDatabaseHas('acc_consolidation_reports', [
            'consolidation_group_id' => $group->id,
            'report_type' => 'consolidated_balance_sheet',
            'status' => 'draft',
        ]);

        $this->assertArrayHasKey($this->subsidiary1->id, $report->consolidated_data);
    }

    public function test_consolidation_group_tracks_ownership_percentage()
    {
        $group = ConsolidationGroup::factory()->create(['parent_company_id' => $this->parentCompany->id]);

        $this->service->addMember($group, [
            'subsidiary_company_id' => $this->subsidiary1->id,
            'ownership_percentage' => 75,
            'relationship_type' => 'subsidiary',
            'acquisition_date' => '2020-01-01',
            'acquisition_price' => 1000000,
        ]);

        $this->service->addMember($group, [
            'subsidiary_company_id' => $this->subsidiary2->id,
            'ownership_percentage' => 25,
            'relationship_type' => 'associate',
            'acquisition_date' => '2021-01-01',
            'acquisition_price' => 500000,
        ]);

        $totalOwnership = $group->getTotalOwnershipPercentage();
        $this->assertEquals(100, $totalOwnership);
    }
}
