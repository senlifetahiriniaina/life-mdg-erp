<?php

namespace Modules\Workflow\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Workflow\Services\WorkflowDslParser;


class WorkflowDslParserTest extends TestCase
{
    private WorkflowDslParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(WorkflowDslParser::class);
    }

    // Use a valid French action from ACTION_MAP
    private function validDsl(string $condition = 'amount > 100000'): string
    {
        return "SI {$condition} ALORS approuver_3_niveaux";
    }

    /** @test */
    public function it_parses_simple_condition()
    {
        $parsed = $this->parser->parse($this->validDsl());

        $this->assertIsArray($parsed);
        $this->assertArrayHasKey('condition', $parsed[0]);
        $this->assertArrayHasKey('action', $parsed[0]);
    }

    /** @test */
    public function it_parses_multiple_conditions()
    {
        $dsl = 'SI status = "approved" ET amount <= 500000 ALORS approuver_3_niveaux';
        $parsed = $this->parser->parse($dsl);

        $this->assertIsArray($parsed);
        $this->assertCount(1, $parsed);
    }

    /** @test */
    public function it_parses_or_conditions()
    {
        $dsl = 'SI montant > 100 OU montant > 200 ALORS approuver_3_niveaux';
        $parsed = $this->parser->parse($dsl);

        $this->assertIsArray($parsed);
        $this->assertCount(1, $parsed);
    }

    /** @test */
    public function it_evaluates_condition_to_true()
    {
        $parsed = $this->parser->parse($this->validDsl());

        $context = ['amount' => 150000];
        $result = $this->parser->evaluate($parsed[0]['condition'], $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_evaluates_condition_to_false()
    {
        $parsed = $this->parser->parse($this->validDsl());

        $context = ['amount' => 50000];
        $result = $this->parser->evaluate($parsed[0]['condition'], $context);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_string_comparison()
    {
        $dsl = 'SI status = "approved" ALORS approuver_3_niveaux';
        $parsed = $this->parser->parse($dsl);

        $context = ['status' => 'approved'];
        $result = $this->parser->evaluate($parsed[0]['condition'], $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_string_not_equal()
    {
        $dsl = 'SI status != "pending" ALORS approuver_3_niveaux';
        $parsed = $this->parser->parse($dsl);

        $context = ['status' => 'approved'];
        $result = $this->parser->evaluate($parsed[0]['condition'], $context);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_extracts_action_name()
    {
        $dsl = 'SI montant > 0 ALORS approuver_3_niveaux';
        $parsed = $this->parser->parse($dsl);

        $this->assertEquals('approval.request_multi_level', $parsed[0]['action']);
    }

    /** @test */
    public function it_validates_dsl_syntax()
    {
        $validDsl = 'SI montant > 100 ALORS approuver_3_niveaux';
        $result = $this->parser->validate($validDsl);

        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function it_rejects_invalid_dsl_syntax()
    {
        $invalidDsl = 'SI ALORS';
        $result = $this->parser->validate($invalidDsl);

        $this->assertFalse($result['valid']);
    }
}
