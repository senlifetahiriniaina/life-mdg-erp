<?php

declare(strict_types=1);

namespace Modules\Workflow\Tests\Feature;

use Modules\Workflow\Services\CodeNodeService;
use Modules\Workflow\Services\Automation\NodeTypeRegistry;
use Tests\TestCase;

/**
 * Tests for Item #23 — Sandboxed expression / code node
 *
 * Covers:
 *  1.  NodeTypeRegistry registers code.expression
 *  2.  NodeTypeRegistry registers code.code_node
 *  3.  Expression: arithmetic evaluation (no eval)
 *  4.  Expression: dot-path context access
 *  5.  Expression: ternary operator
 *  6.  Expression: string concatenation via ~
 *  7.  Expression: comparison returns bool
 *  8.  Validate: blocks PHP/shell injection in expression
 *  9.  Validate: blocks os/sys import in python_safe
 * 10.  Validate: allows clean Python code
 * 11.  Python: sandbox execution returns output dict
 * 12.  Python: blocks eval() usage
 * 13.  Python: timeout is respected (mocked via very short timeout)
 */
class CodeNodeServiceTest extends TestCase
{
    private CodeNodeService $service;
    private NodeTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service  = new CodeNodeService();
        $this->registry = app(NodeTypeRegistry::class);
    }

    // ── Test 1 ──────────────────────────────────────────────────────────────────

    public function test_registry_has_code_expression(): void
    {
        $this->assertTrue($this->registry->has('code.expression'));
        $def = $this->registry->resolve('code.expression');
        $this->assertEquals('transform', $def['category']);
    }

    // ── Test 2 ──────────────────────────────────────────────────────────────────

    public function test_registry_has_code_code_node(): void
    {
        $this->assertTrue($this->registry->has('code.code_node'));
        $def = $this->registry->resolve('code.code_node');
        $this->assertEquals('transform', $def['category']);
    }

    // ── Test 3 ──────────────────────────────────────────────────────────────────

    public function test_expression_arithmetic_evaluation(): void
    {
        $result = $this->service->execute('expression', '2 + 3 * 4', []);

        $this->assertNull($result['error']);
        // Standard operator precedence: multiplication before addition.
        // 2 + 3 * 4 = 2 + (3*4) = 2 + 12 = 14
        $this->assertEqualsWithDelta(14.0, $result['output']['result'], 0.001);
    }

    // ── Test 4 ──────────────────────────────────────────────────────────────────

    public function test_expression_dot_path_context_access(): void
    {
        $result = $this->service->execute(
            'expression',
            '$context.amount * 1.18',
            ['context' => ['amount' => 100000]]
        );

        $this->assertNull($result['error']);
        $this->assertEqualsWithDelta(118000.0, $result['output']['result'], 0.01);
    }

    // ── Test 5 ──────────────────────────────────────────────────────────────────

    public function test_expression_ternary_operator(): void
    {
        $result = $this->service->execute(
            'expression',
            '$context.score > 50 ? "pass" : "fail"',
            ['context' => ['score' => 75]]
        );

        $this->assertNull($result['error']);
        $this->assertEquals('pass', $result['output']['result']);
    }

    // ── Test 6 ──────────────────────────────────────────────────────────────────

    public function test_expression_string_concat_via_tilde(): void
    {
        $result = $this->service->execute(
            'expression',
            '"Hello" ~ " " ~ "World"',
            []
        );

        $this->assertNull($result['error']);
        $this->assertEquals('Hello World', $result['output']['result']);
    }

    // ── Test 7 ──────────────────────────────────────────────────────────────────

    public function test_expression_comparison_returns_bool(): void
    {
        $result = $this->service->execute(
            'expression',
            '$context.total >= 100',
            ['context' => ['total' => 150]]
        );

        $this->assertNull($result['error']);
        $this->assertTrue((bool) $result['output']['result']);
    }

    // ── Test 8 ──────────────────────────────────────────────────────────────────

    public function test_validate_blocks_php_injection_in_expression(): void
    {
        $result = $this->service->validate('expression', 'eval("system(\'ls\')")');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ── Test 9 ──────────────────────────────────────────────────────────────────

    public function test_validate_blocks_os_import_in_python_safe(): void
    {
        $result = $this->service->validate('python_safe', 'import os; os.system("rm -rf /")');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ── Test 10 ─────────────────────────────────────────────────────────────────

    public function test_validate_allows_clean_python_code(): void
    {
        $code = <<<'PYTHON'
numbers = [1, 2, 3, 4, 5]
total = sum(numbers)
output["total"] = total
output["count"] = len(numbers)
PYTHON;

        $result = $this->service->validate('python_safe', $code);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    // ── Test 11 ─────────────────────────────────────────────────────────────────

    /** @group python */
    public function test_python_sandbox_returns_output_dict(): void
    {
        if (!$this->pythonAvailable()) {
            $this->markTestSkipped('python3 not available in this environment');
        }

        $code = <<<'PYTHON'
output["greeting"] = "bonjour"
output["count"] = 42
PYTHON;

        $result = $this->service->execute('python_safe', $code, [], 5);

        $this->assertNull($result['error']);
        $this->assertFalse($result['timed_out']);
        $this->assertEquals('bonjour', $result['output']['greeting']);
        $this->assertEquals(42, $result['output']['count']);
    }

    // ── Test 12 ─────────────────────────────────────────────────────────────────

    public function test_python_blocks_eval_usage(): void
    {
        $result = $this->service->validate('python_safe', 'eval("__import__(\'os\')")');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ── Test 13 ─────────────────────────────────────────────────────────────────

    /** @group python */
    public function test_python_timeout_returns_timed_out_true(): void
    {
        if (!$this->pythonAvailable()) {
            $this->markTestSkipped('python3 not available in this environment');
        }

        // Infinite loop should time out
        $code = <<<'PYTHON'
while True:
    pass
PYTHON;

        $result = $this->service->execute('python_safe', $code, [], 1);

        $this->assertTrue($result['timed_out']);
        $this->assertNotNull($result['error']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function pythonAvailable(): bool
    {
        exec('python3 --version 2>&1', $out, $code);
        return $code === 0;
    }
}
