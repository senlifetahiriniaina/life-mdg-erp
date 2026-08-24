<?php

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Workflow\Services\WorkflowEngineService;
use App\Models\User;

/*
 * Chantier 32.11: it_evaluates_trigger_condition/it_dispatches_action/
 * it_processes_workflow_chain/it_supports_parallel_actions/
 * it_retries_failed_action/it_handles_action_timeout/
 * it_logs_workflow_execution below tested WorkflowEngineService's deleted
 * legacy Cache-based methods (evaluateTrigger/dispatchAction/processChain/
 * dispatchParallelActions/dispatchWithRetry/dispatchWithTimeout/
 * logExecution) — confirmed zero real callers anywhere, deleted this
 * chantier alongside ApprovalWorkflowService/TaskManagementService/
 * WorkflowBuilderService. Removed. The 3 evaluateCondition() tests kept
 * below exercise a still-real, still-live method — unchanged by this
 * chantier.
 */
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

    /**
     * Chantier 32.11: this and the sibling _or test below pass a nested
     * {logic: AND/OR, conditions: [...]} shape — the real
     * evaluateCondition() only ever reads a single flat {operator, field,
     * value} object and has no AND/OR combinator logic at all, so both
     * "pass" only because the missing top-level `operator` key falls to
     * the method's own `'always' => true` default, not because any
     * combinator logic actually runs. Confirmed unrelated to this
     * chantier's changes (evaluateCondition() itself was not touched) —
     * left as-is rather than expanding this chantier's scope into fixing a
     * pre-existing misleading-but-passing test.
     */
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
}
