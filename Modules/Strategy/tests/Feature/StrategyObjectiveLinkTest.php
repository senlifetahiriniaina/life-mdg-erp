<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyPillar;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;
use Modules\Strategy\Services\StrategyObjectiveLinkService;
use App\Models\User;

class StrategyObjectiveLinkTest extends TestCase
{
    private User $user;
    private StrategyPlan $plan;
    private StrategyPillar $pillar;
    private StrategyObjective $objective;
    private StrategyObjectiveLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->pillar = StrategyPillar::create([
            'plan_id' => $this->plan->id,
            'name' => 'Growth',
            'description' => 'Revenue growth pillar',
            'color' => '#FF6B6B',
            'icon' => 'pi-trending-up',
            'sort_order' => 1,
        ]);

        $this->objective = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'pillar_id' => $this->pillar->id,
            'title' => 'Achieve 2B XOF revenue',
            'description' => 'Double revenue in 2 years',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 45,
        ]);

        $this->service = app(StrategyObjectiveLinkService::class);
    }

    /** @test */
    public function it_can_link_a_resource_to_an_objective()
    {
        $link = $this->service->link(
            $this->objective,
            'Accounting/Invoice',
            123,
            50000,
            'XOF'
        );

        $this->assertInstanceOf(StrategyObjectiveLink::class, $link);
        $this->assertEquals($this->objective->id, $link->strategy_objective_id);
        $this->assertEquals('Accounting/Invoice', $link->linkable_type);
        $this->assertEquals(123, $link->linkable_id);
        $this->assertEquals(50000, $link->contribution_value);
        $this->assertEquals('XOF', $link->unit_type);
    }

    /** @test */
    public function it_can_unlink_a_resource()
    {
        $this->service->link($this->objective, 'CRM/Opportunity', 456, 100000);

        $this->assertTrue(
            StrategyObjectiveLink::where('linkable_type', 'CRM/Opportunity')
                ->where('linkable_id', 456)
                ->exists()
        );

        $this->service->unlink('CRM/Opportunity', 456);

        $this->assertFalse(
            StrategyObjectiveLink::where('linkable_type', 'CRM/Opportunity')
                ->where('linkable_id', 456)
                ->exists()
        );
    }

    /** @test */
    public function it_can_get_objective_for_a_resource()
    {
        $this->service->link($this->objective, 'Manufacturing/ProductionOrder', 789);

        $foundObjective = $this->service->getObjectiveForResource(
            'Manufacturing/ProductionOrder',
            789
        );

        $this->assertNotNull($foundObjective);
        $this->assertEquals($this->objective->id, $foundObjective->id);
        $this->assertEquals('Achieve 2B XOF revenue', $foundObjective->title);
    }

    /** @test */
    public function it_returns_null_if_resource_is_not_linked()
    {
        $foundObjective = $this->service->getObjectiveForResource(
            'Sales/Order',
            999
        );

        $this->assertNull($foundObjective);
    }

    /** @test */
    public function it_can_get_full_hierarchy_for_linked_resource()
    {
        $this->service->link($this->objective, 'HR/Employee', 50);

        $hierarchy = $this->service->getResourceHierarchy('HR/Employee', 50);

        $this->assertNotNull($hierarchy['vision']);
        $this->assertNotNull($hierarchy['pillar']);
        $this->assertNotNull($hierarchy['objective']);
        $this->assertEquals($this->plan->name, $hierarchy['vision']['name']);
        $this->assertEquals($this->pillar->name, $hierarchy['pillar']['name']);
        $this->assertEquals($this->objective->title, $hierarchy['objective']['title']);
    }

    /** @test */
    public function it_returns_empty_hierarchy_for_unlinked_resource()
    {
        $hierarchy = $this->service->getResourceHierarchy('Sales/Quotation', 999);

        $this->assertNull($hierarchy['vision']);
        $this->assertNull($hierarchy['pillar']);
        $this->assertNull($hierarchy['objective']);
        $this->assertEquals(0, $hierarchy['progress']);
    }

    /** @test */
    public function it_can_get_linked_resources_paginated()
    {
        $this->service->link($this->objective, 'Accounting/Invoice', 1);
        $this->service->link($this->objective, 'Accounting/Invoice', 2);
        $this->service->link($this->objective, 'Accounting/Invoice', 3);

        $links = $this->service->getLinkedResources($this->objective->id, 1, 10);

        $this->assertEquals(3, $links->total());
        $this->assertEquals(3, $links->count());
    }

    /** @test */
    public function it_can_bulk_link_resources()
    {
        $resourceIds = [10, 20, 30, 40];

        $this->service->bulkLink(
            $this->objective->id,
            'CRM/Opportunity',
            $resourceIds,
            75000
        );

        $this->assertEquals(4, StrategyObjectiveLink::where(
            'strategy_objective_id',
            $this->objective->id
        )->count());

        foreach ($resourceIds as $id) {
            $this->assertTrue(
                StrategyObjectiveLink::where('linkable_type', 'CRM/Opportunity')
                    ->where('linkable_id', $id)
                    ->exists()
            );
        }
    }

    /** @test */
    public function it_can_update_contribution_value()
    {
        $link = $this->service->link($this->objective, 'Inventory/Product', 100, 1000);

        $updated = $this->service->updateContribution($link->id, 1500, 'units');

        $this->assertEquals(1500, $updated->contribution_value);
        $this->assertEquals('units', $updated->unit_type);
    }

    /** @test */
    public function it_can_get_aggregated_contribution()
    {
        $this->service->link($this->objective, 'Accounting/Invoice', 1, 50000, 'XOF');
        $this->service->link($this->objective, 'Accounting/Invoice', 2, 75000, 'XOF');
        $this->service->link($this->objective, 'CRM/Opportunity', 10, 100000, 'XOF');
        $this->service->link($this->objective, 'HR/Employee', 50);

        $aggregated = $this->service->getAggregatedContribution($this->objective->id);

        $this->assertEquals(225000, $aggregated['total_value']);
        $this->assertEquals(4, $aggregated['resource_count']);
        $this->assertArrayHasKey('XOF', $aggregated['by_unit_type']);
    }

    /** @test */
    public function it_can_check_if_resource_is_linked()
    {
        $this->service->link($this->objective, 'Projects/Task', 200);

        $this->assertTrue($this->service->isLinked('Projects/Task', 200));
        $this->assertFalse($this->service->isLinked('Projects/Task', 201));
    }

    /** @test */
    public function it_prevents_duplicate_links()
    {
        $this->service->link($this->objective, 'Sales/Order', 300, 50000);

        // Attempting to create the same link should throw unique constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->service->link($this->objective, 'Sales/Order', 300, 60000);
    }

    /** @test */
    public function it_gets_objectives_with_link_counts()
    {
        $this->service->link($this->objective, 'Accounting/Invoice', 1, 50000);
        $this->service->link($this->objective, 'Accounting/Invoice', 2, 75000);

        $objectives = $this->service->getObjectivesWithLinkCounts($this->plan->id);

        $this->assertTrue($objectives->contains('id', $this->objective->id));
        $found = $objectives->find($this->objective->id);
        $this->assertEquals(2, $found->link_count);
        $this->assertEquals(125000, $found->aggregated['total_value']);
    }

    /** @test */
    public function it_returns_empty_array_for_unlinked_resource_hierarchy()
    {
        $hierarchy = $this->service->getResourceHierarchy('NonExistent/Model', 999);

        $this->assertIsArray($hierarchy);
        $this->assertNull($hierarchy['vision']);
        $this->assertNull($hierarchy['pillar']);
        $this->assertNull($hierarchy['objective']);
    }
}
