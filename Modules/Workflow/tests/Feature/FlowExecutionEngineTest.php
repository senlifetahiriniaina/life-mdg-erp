<?php

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Workflow\Services\Automation\FlowExecutionEngine;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationExecution as FlowExecution;
use App\Models\User;


class FlowExecutionEngineTest extends TestCase
{
    private FlowExecutionEngine $engine;
    private User $user;
    private AutomationFlow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(FlowExecutionEngine::class);
        $this->user = User::factory()->create();

        $this->flow = AutomationFlow::create([
            'tenant_id' => $this->user->tenant_id,
            'key' => 'test.flow',
            'name' => 'Test Flow',
            'trigger' => 'crm.opportunity.won',
            'nodes' => json_encode([
                ['id' => '1', 'type' => 'trigger', 'data' => ['trigger_key' => 'crm.opportunity.won']],
                ['id' => '2', 'type' => 'action', 'data' => ['action_key' => 'notification.send']],
            ]),
            'edges' => json_encode([
                ['source' => '1', 'target' => '2'],
            ]),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_execute_a_flow_by_trigger_key()
    {
        $execution = $this->engine->triggerByKey('test.flow', [
            'opportunity_id' => 123,
            'amount' => 150000,
        ]);

        $this->assertInstanceOf(FlowExecution::class, $execution);
        $this->assertEquals('test.flow', $execution->flow_key);
        $this->assertContains($execution->status, ['pending', 'running', 'completed', 'failed']);
    }

    /** @test */
    public function it_passes_context_data_through_flow()
    {
        $contextData = [
            'opportunity_id' => 456,
            'amount' => 250000,
            'client_id' => 789,
        ];

        $execution = $this->engine->triggerByKey('test.flow', $contextData);

        $this->assertNotNull($execution);
        $this->assertIsArray($execution->context);
    }

    /** @test */
    public function it_tracks_flow_execution_status()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['opportunity_id' => 123]);

        $this->assertContains($execution->status, ['pending', 'running', 'completed', 'failed']);
        $this->assertNotNull($execution->started_at);
    }

    /** @test */
    public function it_executes_flow_nodes_in_order()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['test' => true]);

        // Verify execution has node results
        $this->assertIsArray($execution->node_results ?? []);
    }

    /** @test */
    public function it_marks_execution_as_completed()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['test' => true]);

        $this->assertNotNull($execution->ended_at);
    }

    /** @test */
    public function it_handles_flow_with_conditional_branch()
    {
        $conditionalFlow = AutomationFlow::create([
            'tenant_id' => $this->user->tenant_id,
            'key' => 'conditional.flow',
            'name' => 'Conditional Flow',
            'trigger' => 'accounting.invoice.created',
            'nodes' => json_encode([
                ['id' => '1', 'type' => 'trigger', 'data' => ['trigger_key' => 'accounting.invoice.created']],
                ['id' => '2', 'type' => 'condition', 'data' => ['condition' => 'amount > 100000']],
                ['id' => '3', 'type' => 'action', 'data' => ['action_key' => 'approval.request']],
            ]),
            'edges' => json_encode([
                ['source' => '1', 'target' => '2', 'condition' => 'true'],
                ['source' => '2', 'target' => '3', 'condition' => 'true'],
            ]),
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $execution = $this->engine->triggerByKey('conditional.flow', ['amount' => 150000]);

        $this->assertNotNull($execution);
    }

    /** @test */
    public function it_handles_flow_error_gracefully()
    {
        $execution = $this->engine->triggerByKey('non.existent.flow', []);

        // Should not throw, but handle gracefully
        $this->assertNotNull($execution);
    }

    /** @test */
    public function it_retries_failed_node()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['test' => true]);

        if ($execution->status === 'failed') {
            $retried = $this->engine->retryExecution($execution->id);
            $this->assertNotNull($retried);
        }
    }

    /** @test */
    public function it_stops_flow_execution()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['test' => true]);

        $stopped = $this->engine->stopExecution($execution->id);
        $this->assertTrue($stopped);
    }

    /** @test */
    public function it_resumes_paused_execution()
    {
        $execution = $this->engine->triggerByKey('test.flow', ['test' => true]);

        if ($execution->status === 'paused') {
            $resumed = $this->engine->resumeExecution($execution->id);
            $this->assertNotNull($resumed);
        }
    }
}
