<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyPillar;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;
use App\Models\User;

class StrategyObjectiveLinkControllerTest extends TestCase
{
    private User $user;
    private StrategyPlan $plan;
    private StrategyPillar $pillar;
    private StrategyObjective $objective;

    protected function setUp(): void
    {
        parent::setUp();

        if (\Spatie\Permission\Models\Permission::count() === 0) {
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->actingAs($this->user);

        $this->plan = StrategyPlan::create([
            'tenant_id' => $this->user->tenant_id,
            'name' => 'Strategic Plan 2026',
            'vision' => 'Become market leader',
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
            'description' => 'Revenue growth',
            'color' => '#FF6B6B',
            'sort_order' => 1,
        ]);

        $this->objective = StrategyObjective::create([
            'plan_id' => $this->plan->id,
            'pillar_id' => $this->pillar->id,
            'title' => 'Achieve 2B XOF revenue',
            'description' => 'Double revenue',
            'level' => 1,
            'status' => 'in_progress',
            'progress' => 45,
        ]);
    }

    /** @test */
    public function it_can_get_resource_hierarchy()
    {
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Accounting/Invoice',
            'linkable_id' => 123,
            'contribution_value' => 50000,
            'unit_type' => 'XOF',
        ]);

        $response = $this->getJson('/api/v1/strategy/resource/Accounting/Invoice/123');

        $response->assertOk();
        $response->assertJsonPath('linked', true);
        $response->assertJsonPath('hierarchy.objective.title', 'Achieve 2B XOF revenue');
        $response->assertJsonPath('hierarchy.pillar.name', 'Growth');
        $response->assertJsonPath('hierarchy.vision.name', 'Strategic Plan 2026');
    }

    /** @test */
    public function it_returns_unlinked_status_for_unlinked_resource()
    {
        $response = $this->getJson('/api/v1/strategy/resource/Sales/Order/999');

        $response->assertOk();
        $response->assertJsonPath('linked', false);
        $response->assertJsonPath('hierarchy.objective', null);
    }

    /** @test */
    public function it_can_link_a_resource()
    {
        $response = $this->postJson('/api/v1/strategy/objective-links/link', [
            'objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 456,
            'contribution_value' => 100000,
            'unit_type' => 'XOF',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('link.linkable_type', 'CRM/Opportunity');
        $response->assertJsonPath('link.linkable_id', 456);

        $this->assertDatabaseHas('strategy_objective_links', [
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 456,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_linking()
    {
        $response = $this->postJson('/api/v1/strategy/objective-links/link', [
            'objective_id' => $this->objective->id,
            // Missing linkable_type and linkable_id
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['linkable_type', 'linkable_id']);
    }

    /** @test */
    public function it_validates_objective_exists_when_linking()
    {
        $response = $this->postJson('/api/v1/strategy/objective-links/link', [
            'objective_id' => 9999,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 456,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('objective_id');
    }

    /** @test */
    public function it_can_unlink_by_id()
    {
        $link = StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Manufacturing/ProductionOrder',
            'linkable_id' => 789,
        ]);

        $response = $this->deleteJson("/api/v1/strategy/objective-links/{$link->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('strategy_objective_links', [
            'id' => $link->id,
        ]);
    }

    /** @test */
    public function it_can_unlink_by_type_and_id()
    {
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'HR/Employee',
            'linkable_id' => 50,
        ]);

        $response = $this->deleteJson('/api/v1/strategy/resource/HR/Employee/50');

        $response->assertOk();
        $this->assertDatabaseMissing('strategy_objective_links', [
            'linkable_type' => 'HR/Employee',
            'linkable_id' => 50,
        ]);
    }

    /** @test */
    public function it_can_update_contribution()
    {
        $link = StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Inventory/Product',
            'linkable_id' => 100,
            'contribution_value' => 1000,
            'unit_type' => 'units',
        ]);

        $response = $this->putJson("/api/v1/strategy/objective-links/{$link->id}", [
            'contribution_value' => 1500,
            'unit_type' => 'units',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('strategy_objective_links', [
            'id' => $link->id,
            'contribution_value' => 1500,
        ]);
    }

    /** @test */
    public function it_can_bulk_link_resources()
    {
        $response = $this->postJson('/api/v1/strategy/objective-links/bulk-link', [
            'objective_id' => $this->objective->id,
            'linkable_type' => 'Accounting/Invoice',
            'resource_ids' => [1, 2, 3, 4, 5],
            'contribution_value' => 50000,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('count', 5);

        $this->assertEquals(5, StrategyObjectiveLink::where(
            'strategy_objective_id',
            $this->objective->id
        )->count());
    }

    /** @test */
    public function it_can_get_linked_resources()
    {
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 10,
        ]);
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 20,
        ]);

        $response = $this->getJson("/api/v1/strategy/objective/{$this->objective->id}/links?page=1&per_page=10");

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 2);
        $response->assertJsonPath('pagination.count', 2);
    }

    /** @test */
    public function it_can_get_aggregated_contribution()
    {
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Accounting/Invoice',
            'linkable_id' => 1,
            'contribution_value' => 50000,
            'unit_type' => 'XOF',
        ]);
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Accounting/Invoice',
            'linkable_id' => 2,
            'contribution_value' => 75000,
            'unit_type' => 'XOF',
        ]);
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 10,
            'contribution_value' => 100000,
            'unit_type' => 'XOF',
        ]);

        $response = $this->getJson("/api/v1/strategy/objective/{$this->objective->id}/aggregated");

        $response->assertOk();
        $response->assertJsonPath('aggregated.total_value', 225000);
        $response->assertJsonPath('aggregated.resource_count', 3);
        $response->assertJsonPath('aggregated.by_unit_type.XOF.value', 225000);
        $response->assertJsonPath('aggregated.by_unit_type.XOF.count', 3);
    }

    /** @test */
    public function it_requires_authorization_to_manage_links()
    {
        // Create a user without Strategy management permission
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $response = $this->postJson('/api/v1/strategy/objective-links/link', [
            'objective_id' => $this->objective->id,
            'linkable_type' => 'CRM/Opportunity',
            'linkable_id' => 456,
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function it_can_view_links_with_viewer_permission()
    {
        StrategyObjectiveLink::create([
            'strategy_objective_id' => $this->objective->id,
            'linkable_type' => 'Sales/Order',
            'linkable_id' => 200,
        ]);

        $response = $this->getJson("/api/v1/strategy/objective/{$this->objective->id}/links");

        $response->assertOk();
    }

    /** @test */
    public function it_handles_pagination_in_linked_resources()
    {
        // Create 25 links
        for ($i = 1; $i <= 25; $i++) {
            StrategyObjectiveLink::create([
                'strategy_objective_id' => $this->objective->id,
                'linkable_type' => 'Accounting/Invoice',
                'linkable_id' => $i,
            ]);
        }

        $response = $this->getJson("/api/v1/strategy/objective/{$this->objective->id}/links?page=1&per_page=10");

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 25);
        $response->assertJsonPath('pagination.per_page', 10);
        $response->assertJsonPath('pagination.last_page', 3);
    }
}
