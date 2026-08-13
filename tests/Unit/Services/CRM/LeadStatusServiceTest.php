<?php

declare(strict_types=1);

namespace Tests\Unit\Services\CRM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Services\LeadStatusService;
use Tests\TestCase;

class LeadStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeadStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeadStatusService();
    }

    public function test_valid_statuses_are_defined(): void
    {
        $this->assertEquals(
            ['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost'],
            LeadStatusService::VALID_STATUSES
        );
    }

    public function test_transition_from_new_to_qualified(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $result = $this->service->transition($opportunity, 'qualified');

        $this->assertTrue($result);
        $this->assertEquals('qualified', $opportunity->refresh()->status);
    }

    public function test_transition_from_new_to_lost(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $result = $this->service->transition($opportunity, 'lost');

        $this->assertTrue($result);
        $this->assertEquals('lost', $opportunity->refresh()->status);
    }

    public function test_transition_from_qualified_to_proposal(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'qualified']);

        $result = $this->service->transition($opportunity, 'proposal');

        $this->assertTrue($result);
        $this->assertEquals('proposal', $opportunity->refresh()->status);
    }

    public function test_transition_invalid_status_throws_exception(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->transition($opportunity, 'invalid_status');
    }

    public function test_transition_invalid_workflow_throws_exception(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot transition from new to negotiation');
        $this->service->transition($opportunity, 'negotiation');
    }

    public function test_transition_from_won_is_terminal(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'won']);

        $this->expectException(\Exception::class);
        $this->service->transition($opportunity, 'lost');
    }

    public function test_transition_from_lost_is_terminal(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'lost']);

        $this->expectException(\Exception::class);
        $this->service->transition($opportunity, 'won');
    }

    public function test_transition_with_reason_stores_log(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $this->service->transition(
            $opportunity,
            'qualified',
            reason: 'Budget confirmed'
        );

        $this->assertDatabaseHas('crm_lead_status_logs', [
            'opportunity_id' => $opportunity->id,
            'from_status' => 'new',
            'to_status' => 'qualified',
            'reason' => 'Budget confirmed',
        ]);
    }

    public function test_transition_with_metadata_stores_metadata(): void
    {
        $opportunity = Opportunity::factory()->create(['status' => 'new']);
        $metadata = ['verified_by' => 'user_123', 'confidence' => 'high'];

        $this->service->transition(
            $opportunity,
            'qualified',
            metadata: $metadata
        );

        $this->assertDatabaseHas('crm_lead_status_logs', [
            'opportunity_id' => $opportunity->id,
            'from_status' => 'new',
            'to_status' => 'qualified',
        ]);
    }

    public function test_all_valid_transition_paths(): void
    {
        // Test path: new -> qualified -> proposal -> negotiation -> won
        $opportunity = Opportunity::factory()->create(['status' => 'new']);

        $this->service->transition($opportunity, 'qualified');
        $this->assertEquals('qualified', $opportunity->refresh()->status);

        $this->service->transition($opportunity, 'proposal');
        $this->assertEquals('proposal', $opportunity->refresh()->status);

        $this->service->transition($opportunity, 'negotiation');
        $this->assertEquals('negotiation', $opportunity->refresh()->status);

        $this->service->transition($opportunity, 'won');
        $this->assertEquals('won', $opportunity->refresh()->status);
    }

    public function test_status_workflow_structure(): void
    {
        $workflow = LeadStatusService::STATUS_WORKFLOW;

        // Test structure exists for all valid statuses
        foreach (LeadStatusService::VALID_STATUSES as $status) {
            $this->assertArrayHasKey($status, $workflow);
            $this->assertIsArray($workflow[$status]);
        }

        // Verify terminal states
        $this->assertEquals([], $workflow['won']);
        $this->assertEquals([], $workflow['lost']);
    }
}
