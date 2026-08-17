<?php

namespace Modules\CRM\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Services\LeadScoringService;
use Tests\TestCase;

/**
 * NOTE: originally targeted a phantom `Modules\CRM\Services\CRMService` (never existed in
 * this repo or in the WideHalo source) with a stateless BANT-style array API
 * (`calculateLeadScore()`/`markAsQualified()`/`convertToOpportunity()`) on lead attributes
 * that don't exist in the schema. Real, wired lead scoring lives in
 * `Modules\CRM\Services\LeadScoringService::recalculate(Lead $lead)`, which scores from real
 * CRM data (linked contact, description, pipeline status, estimated value, activity count),
 * is auto-fired by `LeadObserver` on every lead write, and populates `crm_leads.score`.
 * Rewritten below to exercise that real mechanism.
 */
class CRMLeadScoringTest extends TestCase
{
    use RefreshDatabase;

    private LeadScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeadScoringService::class);
    }

    public function test_calculate_lead_score(): void
    {
        $lead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'description' => 'Referred by an existing customer, ready to move forward.',
            'status' => 'proposal',
            'estimated_value' => 50000,
        ]);

        $score = $this->service->recalculate($lead);

        $this->assertGreaterThan(0, $score);
        $this->assertEquals($score, $lead->fresh()->score);
    }

    public function test_high_engagement_leads_prioritized(): void
    {
        // Hot lead: linked contact, description, advanced pipeline stage, high value.
        $hotLead = Lead::factory()->create([
            'contact_id' => Contact::factory()->create()->id,
            'description' => 'Enterprise deal, decision maker engaged.',
            'status' => 'negotiation',
            'estimated_value' => 150000,
        ]);

        // Cold lead: no contact, no description, brand new, no estimated value.
        $coldLead = Lead::factory()->create([
            'contact_id' => null,
            'description' => null,
            'status' => 'new',
            'estimated_value' => null,
        ]);

        $hotScore = $this->service->recalculate($hotLead);
        $coldScore = $this->service->recalculate($coldLead);

        $this->assertGreaterThan($coldScore, $hotScore);
    }

    public function test_qualified_stage_increases_score_over_new_lead(): void
    {
        // Same underlying attributes, differing only by pipeline stage — isolates the
        // status-stage bonus that LeadScoringService::recalculate() awards for progressing
        // a lead through qualification (0 for 'new' vs 25 for 'qualified').
        $newLead = Lead::factory()->create(['status' => 'new']);
        $qualifiedLead = Lead::factory()->create(['status' => 'qualified']);

        $newScore = $this->service->recalculate($newLead);
        $qualifiedScore = $this->service->recalculate($qualifiedLead);

        $this->assertGreaterThan($newScore, $qualifiedScore);
    }
}
