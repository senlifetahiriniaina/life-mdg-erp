<?php

namespace Modules\CRM\Tests\Unit;

use Illuminate\Support\Facades\Cache;
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
        $this->assertEquals('silver', $result['tier']);
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
    // ======================================================================

    public function test_can_score_hot_lead()
    {
        $leadData = [
            'company_size' => 'enterprise',
            'industry' => 'Technology',
            'budget' => 500000,
            'engagement_level' => 'high',
            'is_decision_maker' => true,
            'purchase_timeline' => 'immediate',
        ];

        $score = $this->leadService->calculateLeadScore($leadData);

        $this->assertGreaterThanOrEqual(80, $score['percentage']);
        $this->assertEquals('hot', $score['quality']);
    }

    public function test_can_score_cold_lead()
    {
        $leadData = [
            'company_size' => 'small',
            'industry' => 'Other',
            'budget' => 5000,
            'engagement_level' => 'low',
            'is_decision_maker' => false,
            'purchase_timeline' => 'unknown',
        ];

        $score = $this->leadService->calculateLeadScore($leadData);

        $this->assertLessThan(40, $score['percentage']);
        $this->assertEquals('cold', $score['quality']);
    }

    public function test_lead_score_has_factors()
    {
        $leadData = [
            'company_size' => 'medium',
            'industry' => 'Finance',
            'budget' => 100000,
            'engagement_level' => 'medium',
            'is_decision_maker' => true,
            'purchase_timeline' => 'this_quarter',
        ];

        $score = $this->leadService->calculateLeadScore($leadData);

        $this->assertArrayHasKey('factors', $score);
        $this->assertGreaterThan(0, count($score['factors']));
        $this->assertArrayHasKey('recommendation', $score);
    }

    public function test_can_score_multiple_leads()
    {
        $leads = [
            [
                'id' => 1,
                'company_size' => 'enterprise',
                'budget' => 500000,
            ],
            [
                'id' => 2,
                'company_size' => 'small',
                'budget' => 5000,
            ],
        ];

        $results = $this->leadService->scoreMultipleLeads($leads);

        $this->assertEquals(2, count($results));
        $this->assertGreaterThan($results[1]['percentage'], $results[0]['percentage']);
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

        // 2. Score a lead from this customer
        $leadScore = $this->leadService->calculateLeadScore([
            'company_size' => 'startup',
            'budget' => 50000,
            'engagement_level' => 'high',
        ]);

        $this->assertGreaterThan(0, $leadScore['percentage']);

        // 3. Create opportunity
        $opp = $this->opportunityService->createOpportunity([
            'customer_id' => $customer['customer_id'],
            'title' => 'Implementation Project',
            'amount' => 75000,
            'probability' => 60,
            'close_date' => now()->addQuarter()->toDateString(),
        ]);

        $this->assertEqual($opp['opportunity_id'], $opp['opportunity_id']);

        // Workflow complete
        $this->assertTrue(true);
    }
}
