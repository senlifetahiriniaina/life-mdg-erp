<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyObjective;
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
        // StrategyPlanService::createPlan() requires a non-nullable string
        // tenantId -- UserFactory leaves tenant_id null by default.
        $this->user = User::factory()->create(['tenant_id' => '1']);

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
        $plan = $this->service->createPlan(
            $this->user->tenant_id,
            [
                'name' => 'New Strategic Plan',
                'vision' => 'Global expansion',
                'mission' => 'Scale operations',
                'framework' => 'OKR',
                'period_start' => 2026,
                'period_end' => 2028,
            ],
            $this->user->id
        );

        $this->assertInstanceOf(StrategyPlan::class, $plan);
        $this->assertEquals('New Strategic Plan', $plan->name);
    }

    /** @test */
    public function it_updates_plan_status()
    {
        $updated = $this->service->updatePlan($this->plan->id, ['status' => 'archived']);

        $this->assertInstanceOf(StrategyPlan::class, $updated);
        $this->assertEquals('archived', $updated->status);
        $this->assertEquals('archived', $this->plan->fresh()->status);
    }

    /** @test */
    public function it_calculates_plan_health_score()
    {
        StrategyObjective::factory()->create(['plan_id' => $this->plan->id, 'status' => 'active', 'progress' => 100, 'weight' => 0.3]);
        StrategyObjective::factory()->create(['plan_id' => $this->plan->id, 'status' => 'active', 'progress' => 80, 'weight' => 0.4]);
        StrategyObjective::factory()->create(['plan_id' => $this->plan->id, 'status' => 'active', 'progress' => 60, 'weight' => 0.3]);

        $healthScore = $this->service->computeHealthScore($this->plan);

        $this->assertIsInt($healthScore);
        $this->assertGreaterThanOrEqual(0, $healthScore);
        $this->assertLessThanOrEqual(100, $healthScore);
    }

    /** @test */
    public function it_retrieves_plan_with_all_objectives()
    {
        $planWithDetails = $this->service->getFullTree($this->plan->id);

        $this->assertIsArray($planWithDetails);
        $this->assertArrayHasKey('objectives', $planWithDetails);
    }

    /** @test */
    public function it_duplicates_a_plan()
    {
        $duplicated = $this->service->duplicatePlan($this->plan->id, 'Duplicated Plan 2027');

        $this->assertNotNull($duplicated);
        $this->assertEquals('Duplicated Plan 2027', $duplicated->name);
        $this->assertEquals('draft', $duplicated->status);
        $this->assertNotEquals($this->plan->id, $duplicated->id);
    }

    /** @test */
    public function it_archives_a_plan()
    {
        $archived = $this->service->updatePlan($this->plan->id, ['status' => 'archived']);

        $this->assertEquals('archived', $archived->status);
        $this->assertEquals('archived', $this->plan->fresh()->status);
    }
}
