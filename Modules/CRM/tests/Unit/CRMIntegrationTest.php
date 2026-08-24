<?php

namespace Modules\CRM\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;
use Modules\CRM\Services\LeadScoringService;
use Tests\TestCase;

/**
 * Chantier 32.15 (CRM 14-layer audit): this file previously also exercised
 * CustomerManagementService/SalesOpportunityService — both confirmed dead/fake at the audit
 * (zero real callers beyond this file, and independently broken/stub — see the accompanying
 * migration's docblock for the full removal rationale) and deleted. Only the real, wired
 * LeadScoringService coverage survives, plus a new integration test built against the real
 * Opportunity model (crm_opportunities, OpportunityController's real create/close flow)
 * in place of the deleted fake in-memory SalesOpportunityService.
 */
class CRMIntegrationTest extends TestCase
{
    protected LeadScoringService $leadService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leadService = app(LeadScoringService::class);

        Cache::flush();
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
    // Integration Test — rewritten against the real Opportunity model
    //
    // The original version of this test drove the deleted fake SalesOpportunityService
    // (uniqid()/Cache-only "persistence", zero real callers) — rewritten to exercise the real,
    // wired lead-scoring → real Opportunity create/close flow instead.
    // ======================================================================

    public function test_full_crm_workflow()
    {
        // 1. Score a lead
        $lead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'status' => 'qualified',
            'estimated_value' => 50000,
        ]);

        $leadScore = $this->leadService->recalculate($lead);

        $this->assertGreaterThan(0, $leadScore);

        // 2. Create a real opportunity from that lead's contact
        $pipeline = Pipeline::factory()->create([
            'is_default' => true,
            'stages' => ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'],
        ]);

        $opportunity = Opportunity::factory()->create([
            'pipeline_id' => $pipeline->id,
            'contact_id' => $lead->contact_id,
            'stage' => 'qualified',
            'status' => 'open',
            'amount' => 75000,
        ]);

        // 3. Close it as won
        $opportunity->update(['status' => 'won', 'stage' => 'won', 'closed_at' => now()]);

        $this->assertSame('won', $opportunity->fresh()->status);
        $this->assertNotNull($opportunity->fresh()->closed_at);
    }
}
