<?php

namespace Modules\CRM\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\{Lead, Opportunity};
use Modules\CRM\Services\CRMService;

class CRMLeadScoringTest extends TestCase
{
    use RefreshDatabase;

    private CRMService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CRMService();
    }

    public function test_calculate_lead_score(): void
    {
        $lead = Lead::factory()->create([
            'email_opens' => 5,
            'page_visits' => 10,
            'form_submissions' => 2
        ]);

        $score = $this->service->calculateLeadScore($lead);
        $this->assertGreaterThan(0, $score);
    }

    public function test_high_engagement_leads_prioritized(): void
    {
        $highEngagement = Lead::factory()->create(['email_opens' => 20, 'page_visits' => 50]);
        $lowEngagement = Lead::factory()->create(['email_opens' => 1, 'page_visits' => 2]);

        $highScore = $this->service->calculateLeadScore($highEngagement);
        $lowScore = $this->service->calculateLeadScore($lowEngagement);

        $this->assertGreaterThan($lowScore, $highScore);
    }

    public function test_convert_qualified_lead_to_opportunity(): void
    {
        $lead = Lead::factory()->create();
        $this->service->markAsQualified($lead);

        $opportunity = $this->service->convertToOpportunity($lead);

        $this->assertNotNull($opportunity->id);
        $this->assertEquals('lead', $opportunity->stage);
    }
}
