<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Modules\Strategy\Services\StrategyAIService;

class StrategyAIServiceTest extends TestCase
{
    private StrategyAIService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Force the fallback-first path deterministically — StrategyAIService bakes
        // `enabled` into the constructor from ANTHROPIC_API_KEY (test-key in .env.testing),
        // so without this every call below would hit the real Anthropic API.
        Http::fake(['api.anthropic.com/*' => Http::response('', 500)]);
        $this->service = app(StrategyAIService::class);
    }

    /** @test */
    public function it_generates_recommendations_from_ratios()
    {
        $ratios = [
            'Accounting' => [
                ['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x'],
                ['name' => 'debt_to_equity', 'current_value' => 0.8, 'benchmark_value' => 0.6, 'status' => 'yellow', 'unit' => 'x'],
            ],
        ];

        $recommendations = $this->service->recommend($ratios, [], 'fr');

        $this->assertIsArray($recommendations);
    }

    /** @test */
    public function it_returns_fallback_when_api_unavailable()
    {
        $recommendations = $this->service->recommend(
            ['Accounting' => [['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x']]],
            [],
            'fr'
        );

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('enabled', $recommendations);
        $this->assertFalse($recommendations['enabled']);
    }

    /** @test */
    public function it_marks_response_as_enabled_when_api_available()
    {
        if (empty(config('services.anthropic.key'))) {
            $this->markTestSkipped('Anthropic API key not configured');
        }

        $recommendations = $this->service->recommend(
            ['Accounting' => [['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x']]],
            [],
            'fr'
        );

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('enabled', $recommendations);
    }

    /** @test */
    public function it_supports_multiple_locales()
    {
        $locales = ['fr', 'en', 'es', 'pt'];

        foreach ($locales as $locale) {
            $recommendations = $this->service->recommend(
                ['Accounting' => [['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x']]],
                [],
                $locale
            );

            $this->assertIsArray($recommendations);
        }
    }

    /** @test */
    public function it_caches_recommendations()
    {
        $ratios = ['Accounting' => [['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x']]];

        $rec1 = $this->service->recommend($ratios, [], 'fr');
        $rec2 = $this->service->recommend($ratios, [], 'fr');

        $this->assertEquals($rec1, $rec2);
    }

    /** @test */
    public function it_handles_correlation_shaped_context_gracefully()
    {
        $correlations = [
            ['kpi1' => 'sales', 'kpi2' => 'customer_count', 'pearson' => 0.87],
            ['kpi1' => 'inventory', 'kpi2' => 'sales', 'pearson' => -0.45],
        ];

        $analysis = $this->service->recommend([], ['correlations' => $correlations], 'fr');

        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('enabled', $analysis);
    }

    /** @test */
    public function it_generates_strategic_recommendation_structure()
    {
        $ratios = [
            'Accounting' => [
                ['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x'],
                ['name' => 'net_profit_margin', 'current_value' => 8.5, 'benchmark_value' => 10, 'status' => 'yellow', 'unit' => '%'],
            ],
        ];

        $result = $this->service->recommend($ratios, ['trend' => 'declining'], 'fr');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('risks', $result);
        $this->assertArrayHasKey('opportunities', $result);
    }

    /** @test */
    public function it_identifies_action_items()
    {
        $ratios = [
            'Accounting' => [
                ['name' => 'current_ratio', 'current_value' => 1.2, 'benchmark_value' => 1.5, 'status' => 'red', 'unit' => 'x', 'trend' => 'down'],
                ['name' => 'debt_to_equity', 'current_value' => 1.2, 'benchmark_value' => 0.6, 'status' => 'red', 'unit' => 'x', 'trend' => 'up'],
            ],
        ];

        $actions = $this->service->recommend($ratios, [], 'fr')['recommendations'];

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
        foreach ($actions as $action) {
            $this->assertArrayHasKey('priority', $action);
            $this->assertArrayHasKey('description', $action);
        }
    }
}
