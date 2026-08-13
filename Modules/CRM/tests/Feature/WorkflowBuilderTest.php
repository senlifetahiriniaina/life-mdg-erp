<?php

namespace Modules\CRM\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CRM\Models\Workflow;
use Tests\TestCase;

class WorkflowBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_workflow(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('v1/crm/workflows', [
            'name'           => 'New Opportunity Workflow',
            'description'    => 'Workflow for new opportunities',
            'trigger_type'   => 'opportunity_created',
            'trigger_config' => ['stage' => 'initial'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_workflows', [
            'name'   => 'New Opportunity Workflow',
            'status' => 'draft',
        ]);
    }

    public function test_user_can_save_workflow_design(): void
    {
        $this->actingAs($this->user);

        $workflow = Workflow::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->putJson("v1/crm/workflows/{$workflow->id}/save", [
            'nodes' => [
                [
                    'id'   => 'node-1',
                    'type' => 'trigger',
                    'name' => 'Opportunity Created',
                    'config' => [],
                    'positionX' => 10,
                    'positionY' => 10,
                ],
            ],
            'edges' => [],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $workflow->fresh()->nodes()->count());
    }

    public function test_user_can_activate_workflow(): void
    {
        $this->actingAs($this->user);

        $workflow = Workflow::factory()->create(['owner_id' => $this->user->id]);
        $workflow->nodes()->create([
            'node_id'   => 'node-1',
            'type'      => 'trigger',
            'name'      => 'Trigger',
            'position_x' => 0,
            'position_y' => 0,
        ]);

        $response = $this->postJson("v1/crm/workflows/{$workflow->id}/activate");

        $response->assertStatus(200);
        $this->assertTrue($workflow->fresh()->status === 'active');
    }

    public function test_workflow_cannot_activate_without_nodes(): void
    {
        $this->actingAs($this->user);

        $workflow = Workflow::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->postJson("v1/crm/workflows/{$workflow->id}/activate");

        $response->assertStatus(400);
    }
}
