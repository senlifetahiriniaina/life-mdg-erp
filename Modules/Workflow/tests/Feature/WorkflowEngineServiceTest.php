<?php

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Workflow\Services\WorkflowEngineService;
use App\Models\User;


class WorkflowEngineServiceTest extends TestCase
{
    private WorkflowEngineService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowEngineService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_evaluates_trigger_condition()
    {
        $trigger = [
            'type' => 'event',
            'event_key' => 'crm.opportunity.won',
        ];

        $context = [
            'event_key' => 'crm.opportunity.won',
            'opportunity_id' => 123,
        ];

        $matches = $this->service->evaluateTrigger($trigger, $context);

        $this->assertTrue($matches);
    }

    /** @test */
    public function it_evaluates_condition_rule()
    {
        $rule = [
            'type' => 'condition',
            'field' => 'amount',
            'operator' => '>',
            'value' => 100000,
        ];

        $context = ['amount' => 150000];
        $result = $this->service->evaluateCondition($rule, $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_evaluates_complex_condition_with_and()
    {
        $rule = [
            'type' => 'condition',
            'logic' => 'AND',
            'conditions' => [
                ['field' => 'status', 'operator' => '=', 'value' => 'approved'],
                ['field' => 'amount', 'operator' => '>', 'value' => 100000],
            ],
        ];

        $context = ['status' => 'approved', 'amount' => 150000];
        $result = $this->service->evaluateCondition($rule, $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_evaluates_complex_condition_with_or()
    {
        $rule = [
            'type' => 'condition',
            'logic' => 'OR',
            'conditions' => [
                ['field' => 'role', 'operator' => '=', 'value' => 'admin'],
                ['field' => 'role', 'operator' => '=', 'value' => 'manager'],
            ],
        ];

        $context = ['role' => 'manager'];
        $result = $this->service->evaluateCondition($rule, $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_dispatches_action()
    {
        $action = [
            'type' => 'action',
            'action_key' => 'notification.send',
            'params' => [
                'user_id' => $this->user->id,
                'message' => 'Test notification',
            ],
        ];

        $result = $this->service->dispatchAction($action);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_processes_workflow_chain()
    {
        $chain = [
            ['type' => 'trigger', 'event_key' => 'crm.opportunity.won'],
            ['type' => 'condition', 'field' => 'amount', 'operator' => '>', 'value' => 100000],
            ['type' => 'action', 'action_key' => 'approval.request'],
        ];

        $context = ['amount' => 150000];

        $result = $this->service->processChain($chain, $context);

        $this->assertIsArray($result);
    }

    /** @test */
    public function it_supports_parallel_actions()
    {
        $parallelActions = [
            ['type' => 'action', 'action_key' => 'notification.send', 'params' => []],
            ['type' => 'action', 'action_key' => 'log.record', 'params' => []],
        ];

        $results = $this->service->dispatchParallelActions($parallelActions);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_retries_failed_action()
    {
        $action = ['type' => 'action', 'action_key' => 'http.request'];
        $maxRetries = 3;

        $result = $this->service->dispatchWithRetry($action, maxRetries: $maxRetries);

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_handles_action_timeout()
    {
        $action = ['type' => 'action', 'action_key' => 'long_running.task'];
        $timeout = 5;

        $result = $this->service->dispatchWithTimeout($action, timeout: $timeout);

        $this->assertNotNull($result);
    }

    /** @test */
    public function it_logs_workflow_execution()
    {
        $chain = [
            ['type' => 'trigger', 'event_key' => 'test.event'],
            ['type' => 'action', 'action_key' => 'test.action'],
        ];

        $executionId = $this->service->logExecution($chain, ['test' => true]);

        $this->assertNotNull($executionId);
    }
}
