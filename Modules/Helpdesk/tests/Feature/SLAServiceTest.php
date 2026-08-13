<?php

namespace Modules\Helpdesk\Tests\Feature;

use Tests\TestCase;
use Modules\Helpdesk\Services\SLAService;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\SLAPolicy;
use App\Models\User;
use Carbon\Carbon;

class SLAServiceTest extends TestCase
{
    private SLAService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SLAService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_gets_sla_policy_for_ticket()
    {
        $policy = SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Critical SLA',
            'priority' => 'critical',
            'category' => 'bug',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'critical',
            'category' => 'bug',
        ]);

        $foundPolicy = $this->service->getPolicyForTicket($ticket);

        $this->assertNotNull($foundPolicy);
        $this->assertEquals($policy->id, $foundPolicy->id);
    }

    /** @test */
    public function it_calculates_response_target()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'feature',
            'response_time_hours' => 2,
            'resolution_time_hours' => 8,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'feature',
            'created_at' => now(),
        ]);

        $target = $this->service->getResponseTarget($ticket);

        $this->assertNotNull($target);
        $this->assertTrue($target->isAfter($ticket->created_at));
    }

    /** @test */
    public function it_detects_response_breach()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 1,
            'resolution_time_hours' => 8,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'support',
            'created_at' => now()->subHours(2),
            'first_response_at' => now()->subHours(1),
        ]);

        $breached = $this->service->isResponseBreached($ticket);

        $this->assertTrue($breached);
    }

    /** @test */
    public function it_detects_resolution_breach()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'support',
            'created_at' => now()->subHours(5),
            'resolved_at' => now(),
        ]);

        $breached = $this->service->isResolutionBreached($ticket);

        $this->assertTrue($breached);
    }

    /** @test */
    public function it_gets_sla_status()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 24,
            'resolution_time_hours' => 48,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'support',
            'created_at' => now(),
        ]);

        $status = $this->service->getStatus($ticket);

        $this->assertIn($status, ['met', 'at_risk', 'breached']);
    }

    /** @test */
    public function it_calculates_time_remaining()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 2,
            'resolution_time_hours' => 8,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'support',
            'created_at' => now(),
        ]);

        $remaining = $this->service->getTimeRemaining($ticket);

        $this->assertIsFloat($remaining);
        $this->assertGreaterThan(0, $remaining);
    }

    /** @test */
    public function it_gets_team_metrics()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 2,
            'resolution_time_hours' => 8,
            'is_active' => true,
        ]);

        $team = Team::factory()->create(['tenant_id' => $this->user->tenant_id]);
        
        Ticket::factory(5)->create([
            'tenant_id' => $this->user->tenant_id,
            'team_id' => $team->id,
            'priority' => 'high',
            'category' => 'support',
        ]);

        $metrics = $this->service->getTeamMetrics($team->id, now()->subDays(7), now());

        $this->assertArrayHasKey('total_tickets', $metrics);
        $this->assertArrayHasKey('compliance_pct', $metrics);
        $this->assertEquals(5, $metrics['total_tickets']);
    }

    /** @test */
    public function it_finds_at_risk_tickets()
    {
        SLAPolicy::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'High SLA',
            'priority' => 'high',
            'category' => 'support',
            'response_time_hours' => 1,
            'resolution_time_hours' => 4,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'priority' => 'high',
            'category' => 'support',
            'created_at' => now()->subMinutes(50), // 50 minutes in, SLA is 60
        ]);

        $atRisk = $this->service->getAtRiskTickets($this->user->tenant_id);

        $this->assertNotEmpty($atRisk);
    }
}
