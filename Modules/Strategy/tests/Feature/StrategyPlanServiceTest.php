<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Services\StrategyPlanService;
use App\Models\User;

class StrategyPlanServiceTest extends TestCase
{
    private StrategyPlanService $service;
    private User $user;
    private StrategyPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StrategyPlanService::class);
        $this->user = User::factory()->create();

        $this->plan = StrategyPlan::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Strategic Plan 2026',
            'vision' => 'Become market leader',
            'mission' => 'Deliver excellence',
            'period_start' => 2026,
            'period_end' => 2028,
            'framework' => 'OKR',
            'status' => 'active',
            'health_score' => 75,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_creates_a_strategy_plan()
    {
        $plan = $this->service->create(
            tenantId: $this->user->tenant_id,
            name: 'New Strategic Plan',
            vision: 'Global expansion',
            mission: 'Scale operations',
            framework: 'OKR',
            periodStart: 2026,
            periodEnd: 2028,
            createdBy: $this->user->id
        );

        $this->assertInstanceOf(StrategyPlan::class, $plan);
        $this->assertEquals('New Strategic Plan', $plan->name);
    }

    /** @test */
    public function it_updates_plan_status()
    {
        $updated = $this->service->updateStatus($this->plan->id, 'completed');

        $this->assertTrue($updated);
        $this->assertEquals('completed', $this->plan->fresh()->status);
    }

    /** @test */
    public function it_calculates_plan_health_score()
    {
        $objectives = [
            ['progress' => 100, 'weight' => 0.3],
            ['progress' => 80, 'weight' => 0.4],
            ['progress' => 60, 'weight' => 0.3],
        ];

        $healthScore = $this->service->calculateHealthScore($objectives);

        $this->assertIsFloat($healthScore);
        $this->assertGreaterThanOrEqual(0, $healthScore);
        $this->assertLessThanOrEqual(100, $healthScore);
    }

    /** @test */
    public function it_retrieves_plan_with_all_objectives()
    {
        $planWithDetails = $this->service->getWithDetails($this->plan->id);

        $this->assertNotNull($planWithDetails);
        $this->assertArrayHasKey('objectives', $planWithDetails);
    }

    /** @test */
    public function it_duplicates_a_plan()
    {
        $duplicated = $this->service->duplicate(
            $this->plan->id,
            newName: 'Duplicated Plan 2027',
            newPeriod: ['start' => 2027, 'end' => 2029]
        );

        $this->assertNotNull($duplicated);
        $this->assertEquals('Duplicated Plan 2027', $duplicated->name);
        $this->assertEquals(2027, $duplicated->period_start);
    }

    /** @test */
    public function it_archives_a_plan()
    {
        $archived = $this->service->archive($this->plan->id);

        $this->assertTrue($archived);
        $this->assertEquals('archived', $this->plan->fresh()->status);
    }

    /** @test */
    public function it_publishes_plan_to_organization()
    {
        $published = $this->service->publish($this->plan->id);

        $this->assertTrue($published);
        $this->assertEquals('published', $this->plan->fresh()->status);
    }
}
