<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Services\OkrService;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyKeyResult;
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
        $objective = $this->service->createObjective([
            'plan_id' => $this->plan->id,
            'title' => 'Double revenue',
            'description' => 'Increase revenue from XOF 1B to XOF 2B',
            'level' => 'annual',
            'weight' => 0.4,
        ]);

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

        $kr = $this->service->addKeyResult($objective->id, [
            'title' => 'KR1: Increase customer base',
            'target_value' => 1000,
            'unit' => 'customers',
        ]);

        $this->assertNotNull($kr);
        $this->assertEquals(1000, $kr->target_value);
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

        $kr = $this->service->addKeyResult($objective->id, ['title' => 'KR1', 'target_value' => 100, 'unit' => 'units']);

        $updated = $this->service->updateKeyResultProgress($kr->id, 75);

        $this->assertInstanceOf(StrategyKeyResult::class, $updated);
        $this->assertEquals(75, $updated->current_value);
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

        $kr1 = $this->service->addKeyResult($objective->id, ['title' => 'KR1', 'target_value' => 100, 'unit' => 'units']);
        $kr2 = $this->service->addKeyResult($objective->id, ['title' => 'KR2', 'target_value' => 100, 'unit' => 'units']);

        $this->service->updateKeyResultProgress($kr1->id, 40);
        $this->service->updateKeyResultProgress($kr2->id, 60);

        $progress = $objective->fresh()->progress;

        $this->assertIsFloat($progress);
        $this->assertGreaterThanOrEqual(0, $progress);
        $this->assertLessThanOrEqual(100, $progress);
    }

    /** @test */
    public function it_cascades_a_child_objective_from_parent()
    {
        $parent = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'title' => 'Parent objective',
            'description' => 'Parent',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 50,
        ]);

        $child = $this->service->cascadeObjective($parent->id, [
            'title' => 'Child objective',
            'level' => 'quarterly',
        ]);

        $this->assertInstanceOf(StrategyObjective::class, $child);
        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals($parent->plan_id, $child->plan_id);
    }
}
