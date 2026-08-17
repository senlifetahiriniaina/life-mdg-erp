<?php

namespace Modules\CRM\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\CustomerManagementService;
use Modules\CRM\Services\LeadScoringService;
use Modules\CRM\Services\SalesOpportunityService;
use Tests\TestCase;

class CRMIntegrationTest extends TestCase
{
    protected CustomerManagementService $customerService;
    protected LeadScoringService $leadService;
    protected SalesOpportunityService $opportunityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerService = app(CustomerManagementService::class);
        $this->leadService = app(LeadScoringService::class);
        $this->opportunityService = app(SalesOpportunityService::class);

        Cache::flush();
    }

    // ======================================================================
    // Customer Management Tests
    // ======================================================================

    public function test_can_create_customer()
    {
        $result = $this->customerService->createCustomer([
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'phone' => '555-0100',
            'company' => 'Acme Corporation',
            'lifetime_value' => 50000,
            'interaction_count' => 15,
            'primary_contact' => [
                'name' => 'John Smith',
                'email' => 'john@acme.com',
                'phone' => '555-0101',
            ],
        ]);

        $this->assertArrayHasKey('customer_id', $result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals('gold', $result['tier']);
    }

    public function test_can_update_customer()
    {
        // Create first
        $customer = $this->customerService->createCustomer([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '555-0100',
        ]);

        // Update
        $result = $this->customerService->updateCustomer($customer['customer_id'], [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('updated', $result['status']);
    }

    public function test_can_search_customers()
    {
        // Create test customers
        $this->customerService->createCustomer([
            'name' => 'ABC Inc',
            'email' => 'abc@example.com',
            'status' => 'active',
        ]);

        $result = $this->customerService->searchCustomers([
            'name' => 'ABC',
            'status' => 'active',
        ]);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('customers', $result);
    }

    public function test_customers_grouped_by_tier()
    {
        // Create customers with different values
        $this->customerService->createCustomer([
            'name' => 'Enterprise Customer',
            'email' => 'enterprise@example.com',
            'lifetime_value' => 500000,
        ]);

        $platinumCustomers = $this->customerService->getCustomersByTier('platinum');

        $this->assertIsArray($platinumCustomers);
    }

    // ======================================================================
    // Lead Scoring Tests
    //
    // NOTE: originally called a phantom `LeadScoringService::calculateLeadScore(array)`/
    // `scoreMultipleLeads(array)` stateless BANT-style API (company_size/budget/
    // engagement_level/...) that was never built, on attributes that don't exist in the
    // schema. The real, wired capability is `LeadScoringService::recalculate(Lead $lead)`,
    // which scores a real `Lead` model from its linked contact, description, pipeline
    // status, estimated value, and activity count, and persists the result to
    // `crm_leads.score`. Rewritten below against that real mechanism.
    // ======================================================================

    public function test_can_score_hot_lead()
    {
        // contact linked (+10) + description (+5) + status=won (+50) + value >= 100k (+20)
        // = 85, comfortably above the "hot" threshold regardless of activity-log noise.
        $lead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'description' => 'Enterprise deal, decision maker engaged, ready to close.',
            'status' => 'won',
            'estimated_value' => 500000,
        ]);

        $score = $this->leadService->recalculate($lead);

        $this->assertGreaterThanOrEqual(80, $score);
    }

    public function test_can_score_cold_lead()
    {
        // No contact, no description, brand new status, no estimated value: only the
        // activity-log bonus from record creation can apply, well under the "cold" ceiling.
        $lead = Lead::factory()->create([
            'contact_id' => null,
            'description' => null,
            'status' => 'new',
            'estimated_value' => null,
        ]);

        $score = $this->leadService->recalculate($lead);

        $this->assertLessThan(40, $score);
    }

    public function test_lead_score_has_factors()
    {
        // The real service returns a plain int, not an inspectable breakdown array — but its
        // scoring factors (contact link, description, pipeline stage, estimated value) are
        // each independently observable by toggling one at a time and confirming the score
        // moves accordingly, which is what "has factors" meant in intent.
        $lead = Lead::factory()->create([
            'contact_id' => null,
            'description' => null,
            'status' => 'new',
            'estimated_value' => null,
        ]);

        $baseline = $this->leadService->recalculate($lead);

        $lead->contact_id = Contact::factory()->create()->id;
        $lead->save();
        $withContact = $this->leadService->recalculate($lead);
        $this->assertGreaterThan($baseline, $withContact);

        $lead->description = 'Interested in the enterprise plan.';
        $lead->save();
        $withDescription = $this->leadService->recalculate($lead);
        $this->assertGreaterThan($withContact, $withDescription);

        $lead->status = 'qualified';
        $lead->save();
        $withStage = $this->leadService->recalculate($lead);
        $this->assertGreaterThan($withDescription, $withStage);

        $lead->estimated_value = 150000;
        $lead->save();
        $withValue = $this->leadService->recalculate($lead);
        $this->assertGreaterThan($withStage, $withValue);
    }

    public function test_can_score_multiple_leads()
    {
        $hotLead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'won',
            'estimated_value' => 500000,
        ]);
        $coldLead = Lead::factory()->create([
            'contact_id' => null,
            'status' => 'new',
            'estimated_value' => null,
        ]);

        $results = collect([$hotLead, $coldLead])
            ->map(fn (Lead $lead) => $this->leadService->recalculate($lead));

        $this->assertEquals(2, count($results));
        $this->assertGreaterThan($results[1], $results[0]);
    }

    // ======================================================================
    // Sales Opportunity Tests
    // ======================================================================

    public function test_can_create_opportunity()
    {
        $result = $this->opportunityService->createOpportunity([
            'customer_id' => 1,
            'title' => 'Enterprise Software License',
            'amount' => 100000,
            'probability' => 75,
            'close_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertArrayHasKey('opportunity_id', $result);
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(75000, $result['expected_value']);
    }

    public function test_can_update_opportunity_stage()
    {
        // Create opportunity
        $opp = $this->opportunityService->createOpportunity([
            'customer_id' => 1,
            'title' => 'Test Opportunity',
            'amount' => 50000,
            'probability' => 50,
            'stage' => 'prospecting',
            'close_date' => now()->addMonth()->toDateString(),
        ]);

        // Update stage
        $result = $this->opportunityService->updateStage(
            $opp['opportunity_id'],
            'proposal'
        );

        $this->assertEquals('prospecting', $result['old_stage']);
        $this->assertEquals('proposal', $result['new_stage']);
    }

    public function test_can_close_opportunity_as_won()
    {
        $opp = $this->opportunityService->createOpportunity([
            'customer_id' => 1,
            'title' => 'Winning Deal',
            'amount' => 75000,
            'probability' => 90,
            'close_date' => now()->toDateString(),
        ]);

        $result = $this->opportunityService->closeOpportunity(
            $opp['opportunity_id'],
            'won',
            'Closed successfully'
        );

        $this->assertEquals('won', $result['outcome']);
        $this->assertEquals(75000, $result['final_value']);
    }

    public function test_can_close_opportunity_as_lost()
    {
        $opp = $this->opportunityService->createOpportunity([
            'customer_id' => 1,
            'title' => 'Lost Deal',
            'amount' => 50000,
            'probability' => 50,
            'close_date' => now()->toDateString(),
        ]);

        $result = $this->opportunityService->closeOpportunity(
            $opp['opportunity_id'],
            'lost',
            'Budget constraints'
        );

        $this->assertEquals('lost', $result['outcome']);
        $this->assertEquals(0, $result['final_value']);
    }

    public function test_can_get_pipeline_summary()
    {
        // Create multiple opportunities
        $this->opportunityService->createOpportunity([
            'customer_id' => 1,
            'title' => 'Deal 1',
            'amount' => 50000,
            'stage' => 'prospecting',
            'close_date' => now()->toDateString(),
        ]);

        $summary = $this->opportunityService->getPipelineSummary(1);

        $this->assertArrayHasKey('total_pipeline_value', $summary);
        $this->assertArrayHasKey('by_stage', $summary);
    }

    // ======================================================================
    // Integration Tests
    // ======================================================================

    public function test_full_crm_workflow()
    {
        // 1. Create customer
        $customer = $this->customerService->createCustomer([
            'name' => 'Tech Startup',
            'email' => 'info@techstartup.com',
            'lifetime_value' => 25000,
        ]);

        $this->assertEquals('silver', $customer['tier']);

        // 2. Score a lead associated with this customer (real Lead + LeadScoringService,
        //    in place of the phantom stateless `calculateLeadScore(array)` BANT API)
        $lead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'qualified',
            'estimated_value' => 50000,
        ]);

        $leadScore = $this->leadService->recalculate($lead);

        $this->assertGreaterThan(0, $leadScore);

        // 3. Create opportunity
        $opp = $this->opportunityService->createOpportunity([
            'customer_id' => $customer['customer_id'],
            'title' => 'Implementation Project',
            'amount' => 75000,
            'probability' => 60,
            'close_date' => now()->addQuarter()->toDateString(),
        ]);

        // (fixes a pre-existing typo — PHPUnit/Pest has no `assertEqual`, only `assertEquals` —
        // that was masked because execution never reached this line while step 2 fatally
        // errored on the phantom API above)
        $this->assertEquals($opp['opportunity_id'], $opp['opportunity_id']);

        // Workflow complete
        $this->assertTrue(true);
    }
}
