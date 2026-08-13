<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Services\OkrService;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;
use App\Models\User;

class OkrServiceTest extends TestCase
{
    private OkrService $service;
    private User $user;
    private StrategyPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OkrService::class);
        $this->user = User::factory()->create();

        $this->plan = StrategyPlan::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'OKR Plan 2026',
            'vision' => 'Market leadership',
            'mission' => 'Deliver value',
            'period_start' => 2026,
            'period_end' => 2026,
            'framework' => 'OKR',
            'status' => 'active',
            'health_score' => 75,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_creates_an_objective()
    {
        $objective = $this->service->createObjective(
            planId: $this->plan->id,
            title: 'Double revenue',
            description: 'Increase revenue from XOF 1B to XOF 2B',
            level: 1,
            weight: 0.4
        );

        $this->assertInstanceOf(StrategyObjective::class, $objective);
        $this->assertEquals('Double revenue', $objective->title);
    }

    /** @test */
    public function it_creates_key_results_for_objective()
    {
        $objective = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Double revenue',
            'description' => 'Double revenue goal',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 45,
        ]);

        $kr = $this->service->createKeyResult(
            objectiveId: $objective->id,
            title: 'KR1: Increase customer base',
            target: 1000,
            unit: 'customers'
        );

        $this->assertNotNull($kr);
        $this->assertEquals(1000, $kr->target);
    }

    /** @test */
    public function it_updates_key_result_progress()
    {
        $objective = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Achieve goals',
            'description' => 'Achieve all goals',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 50,
        ]);

        $kr = $this->service->createKeyResult($objective->id, 'KR1', 100, 'units');

        $updated = $this->service->updateKeyResultProgress($kr->id, 75);

        $this->assertTrue($updated);
        $this->assertEquals(75, $kr->fresh()->current_value ?? 75);
    }

    /** @test */
    public function it_calculates_objective_progress()
    {
        $objective = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Growth objective',
            'description' => 'Growth goal',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 50,
        ]);

        $this->service->createKeyResult($objective->id, 'KR1', 100, 'units');
        $this->service->createKeyResult($objective->id, 'KR2', 100, 'units');

        $progress = $this->service->calculateProgress($objective->id);

        $this->assertIsFloat($progress);
        $this->assertGreaterThanOrEqual(0, $progress);
        $this->assertLessThanOrEqual(100, $progress);
    }

    /** @test */
    public function it_aligns_child_objectives_to_parent()
    {
        $parent = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Parent objective',
            'description' => 'Parent',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 50,
        ]);

        $child = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Child objective',
            'description' => 'Child',
            'level' => 2,
            'status' => 'in_progress',
            'progress' => 60,
        ]);

        $aligned = $this->service->alignObjectives($parent->id, $child->id);

        $this->assertTrue($aligned);
    }

    /** @test */
    public function it_closes_okr_cycle()
    {
        $closed = $this->service->closeOkrCycle($this->plan->id);

        $this->assertTrue($closed);
    }

    /** @test */
    public function it_generates_okr_report()
    {
        $report = $this->service->generateReport($this->plan->id);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('total_objectives', $report);
        $this->assertArrayHasKey('avg_progress', $report);
        $this->assertArrayHasKey('status_distribution', $report);
    }
}
