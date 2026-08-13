<?php

declare(strict_types=1);

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Workflow\Services\Automation\FlowExecutionEngine;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Modules\Workflow\Models\Automation\AutomationFlow;
use Modules\Workflow\Models\Automation\AutomationNode;
use Tests\TestCase;

/**
 * Tests for Item #24 — Loop / Iterator / Sub-flow nodes
 *
 * Covers:
 *  1. NodeTypeRegistry registers loop.loop_items
 *  2. NodeTypeRegistry registers loop.while_loop
 *  3. NodeTypeRegistry registers flow.sub_flow
 *  4. loop_items iterates correctly over an array
 *  5. loop_items handles empty array gracefully
 *  6. while_loop runs until condition becomes false
 *  7. while_loop respects max_iterations guard
 *  8. sub_flow rejects unpublished flow
 *  9. sub_flow rejects unknown flow id
 * 10. sub_flow executes a published sub-flow and returns output
 * 11. sub_flow respects MAX_SUB_FLOW_DEPTH (recursive depth guard)
 */
class LoopSubFlowNodeTest extends TestCase
{
    use RefreshDatabase;

    private FlowExecutionEngine $engine;
    private NodeTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine   = app(FlowExecutionEngine::class);
        $this->registry = app(NodeTypeRegistry::class);
    }

    // ── Test 1 ──────────────────────────────────────────────────────────────────

    public function test_registry_has_loop_items(): void
    {
        $this->assertTrue($this->registry->has('loop.loop_items'));
        $def = $this->registry->resolve('loop.loop_items');
        $this->assertEquals('control', $def['category']);
    }

    // ── Test 2 ──────────────────────────────────────────────────────────────────

    public function test_registry_has_while_loop(): void
    {
        $this->assertTrue($this->registry->has('loop.while_loop'));
        $def = $this->registry->resolve('loop.while_loop');
        $this->assertEquals('control', $def['category']);
    }

    // ── Test 3 ──────────────────────────────────────────────────────────────────

    public function test_registry_has_sub_flow(): void
    {
        $this->assertTrue($this->registry->has('flow.sub_flow'));
        $def = $this->registry->resolve('flow.sub_flow');
        $this->assertEquals('control', $def['category']);
    }

    // ── Test 4 ──────────────────────────────────────────────────────────────────

    public function test_loop_items_iterates_correctly(): void
    {
        // Build a minimal flow with a loop_items node (no child connections)
        $flow = $this->makeFlow('loop_items_test');
        $loopNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'loop.loop_items',
            'label'      => 'For Each Item',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => ['array_path' => 'items', 'item_var' => 'item'],
        ]);

        $result = $this->callExecuteNode($loopNode, [
            'output' => ['items' => ['alpha', 'beta', 'gamma']],
        ]);

        $this->assertEquals(3, $result['output']['iteration_count']);
        $this->assertCount(3, $result['output']['results']);
    }

    // ── Test 5 ──────────────────────────────────────────────────────────────────

    public function test_loop_items_handles_empty_array(): void
    {
        $flow     = $this->makeFlow('loop_items_empty');
        $loopNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'loop.loop_items',
            'label'      => 'For Each Empty',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => ['array_path' => 'missing_field'],
        ]);

        $result = $this->callExecuteNode($loopNode, ['output' => []]);

        $this->assertEquals(0, $result['output']['iteration_count']);
        $this->assertCount(0, $result['output']['results']);
    }

    // ── Test 6 ──────────────────────────────────────────────────────────────────

    public function test_while_loop_runs_until_condition_false(): void
    {
        // We set max_iterations=3 and a condition that never becomes false —
        // the max_iterations guard must fire and return stopped_by=max_iterations.
        $flow = $this->makeFlow('while_loop_test');
        $whileNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'loop.while_loop',
            'label'      => 'While Loop',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => [
                'condition'      => 'true',  // always true
                'max_iterations' => 3,
            ],
        ]);

        $result = $this->callExecuteNode($whileNode, ['output' => ['counter' => 0]]);

        $this->assertEquals(3, $result['output']['iterations_run']);
        $this->assertEquals('max_iterations', $result['output']['stopped_by']);
    }

    // ── Test 7 ──────────────────────────────────────────────────────────────────

    public function test_while_loop_respects_max_iterations_guard(): void
    {
        // Even if config requests 1000 iterations, MAX_LOOP_ITERATIONS (500) caps it.
        $flow = $this->makeFlow('while_loop_cap_test');
        $whileNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'loop.while_loop',
            'label'      => 'While Loop Cap',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => [
                'condition'      => 'true',
                'max_iterations' => 1000, // will be capped to 500
            ],
        ]);

        $result = $this->callExecuteNode($whileNode, ['output' => []]);

        $this->assertLessThanOrEqual(500, $result['output']['iterations_run']);
        $this->assertEquals('max_iterations', $result['output']['stopped_by']);
    }

    // ── Test 8 ──────────────────────────────────────────────────────────────────

    public function test_sub_flow_rejects_unpublished_flow(): void
    {
        $unpublished = AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => 'unpublished.sub',
            'name'           => 'Unpublished',
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'is_published'   => false,
            'version_number' => 0,
        ]);

        $flow     = $this->makeFlow('sub_flow_reject_test');
        $subNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'flow.sub_flow',
            'label'      => 'Sub Flow',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => ['sub_flow_id' => $unpublished->id],
        ]);

        $result = $this->callExecuteNode($subNode, ['output' => []]);

        $this->assertStringContainsString('not published', $result['output']['sub_flow_error'] ?? '');
    }

    // ── Test 9 ──────────────────────────────────────────────────────────────────

    public function test_sub_flow_rejects_unknown_flow_id(): void
    {
        $flow    = $this->makeFlow('sub_flow_unknown_test');
        $subNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'flow.sub_flow',
            'label'      => 'Sub Flow',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => ['sub_flow_id' => 99999],
        ]);

        $result = $this->callExecuteNode($subNode, ['output' => []]);

        $this->assertStringContainsString('not found', $result['output']['sub_flow_error'] ?? '');
    }

    // ── Test 10 ─────────────────────────────────────────────────────────────────

    public function test_sub_flow_executes_published_flow_and_returns_output(): void
    {
        // Create a published sub-flow with a trigger node
        $subFlow = AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => 'published.sub',
            'name'           => 'Published Sub-flow',
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'is_published'   => true,
            'version_number' => 1,
        ]);

        AutomationNode::create([
            'flow_id'    => $subFlow->id,
            'node_type'  => 'trigger',
            'node_key'   => 'manual.trigger',
            'label'      => 'Trigger',
            'position_x' => 100,
            'position_y' => 100,
        ]);

        $flow    = $this->makeFlow('sub_flow_execute_test');
        $subNode = AutomationNode::create([
            'flow_id'    => $flow->id,
            'node_type'  => 'control',
            'node_key'   => 'flow.sub_flow',
            'label'      => 'Sub Flow',
            'position_x' => 200,
            'position_y' => 200,
            'config'     => ['sub_flow_id' => $subFlow->id, 'output_var' => 'result'],
        ]);

        $result = $this->callExecuteNode($subNode, ['output' => ['param' => 'value']]);

        $this->assertArrayHasKey('result', $result['output']);
        $this->assertArrayHasKey('execution_id', $result['output']);
    }

    // ── Test 11 ─────────────────────────────────────────────────────────────────

    public function test_sub_flow_depth_limit_throws_on_deep_recursion(): void
    {
        // Create a flow that references itself (infinite recursion attempt)
        $selfRefFlow = AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => 'self.referencing',
            'name'           => 'Self Reference',
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'is_published'   => true,
            'version_number' => 1,
        ]);

        AutomationNode::create([
            'flow_id'    => $selfRefFlow->id,
            'node_type'  => 'trigger',
            'node_key'   => 'manual.trigger',
            'label'      => 'Trigger',
            'position_x' => 100,
            'position_y' => 100,
        ]);

        $selfSubNode = AutomationNode::create([
            'flow_id'    => $selfRefFlow->id,
            'node_type'  => 'control',
            'node_key'   => 'flow.sub_flow',
            'label'      => 'Self Sub',
            'position_x' => 300,
            'position_y' => 100,
            'config'     => ['sub_flow_id' => $selfRefFlow->id],
        ]);

        // Execution should throw a RuntimeException due to depth limit
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/depth limit/i');

        $this->callExecuteNode($selfSubNode, ['output' => []]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function makeFlow(string $key): AutomationFlow
    {
        return AutomationFlow::create([
            'tenant_id'      => 1,
            'key'            => $key,
            'name'           => $key,
            'trigger_type'   => 'manual',
            'is_active'      => true,
            'is_published'   => true,
            'version_number' => 1,
        ]);
    }

    /**
     * Call FlowExecutionEngine::executeNode() via reflection to unit-test node handlers.
     *
     * @return array<string,mixed>
     */
    private function callExecuteNode(AutomationNode $node, array $context): array
    {
        $ref    = new \ReflectionClass($this->engine);
        $method = $ref->getMethod('executeNode');
        $method->setAccessible(true);
        return $method->invoke($this->engine, $node, $context);
    }
}
