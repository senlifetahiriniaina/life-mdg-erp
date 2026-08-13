<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Services\StrategyAIService;

class StrategyAIServiceTest extends TestCase
{
    private StrategyAIService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StrategyAIService::class);
    }

    /** @test */
    public function it_generates_recommendations_from_ratios()
    {
        $ratios = [
            'current_ratio' => ['value' => 1.2, 'benchmark' => 1.5, 'status' => 'red'],
            'debt_to_equity' => ['value' => 0.8, 'benchmark' => 0.6, 'status' => 'yellow'],
        ];

        $recommendations = $this->service->generateRecommendations(
            tenantId: 1,
            module: 'Accounting',
            ratios: $ratios,
            locale: 'fr'
        );

        $this->assertIsArray($recommendations);
    }

    /** @test */
    public function it_returns_fallback_when_api_unavailable()
    {
        $this->app['config']->set('services.anthropic.key', null);

        $recommendations = $this->service->generateRecommendations(
            tenantId: 1,
            module: 'Accounting',
            ratios: ['current_ratio' => ['value' => 1.2, 'benchmark' => 1.5]],
            locale: 'fr'
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

        $recommendations = $this->service->generateRecommendations(
            tenantId: 1,
            module: 'Accounting',
            ratios: ['current_ratio' => ['value' => 1.2, 'benchmark' => 1.5]],
            locale: 'fr'
        );

        $this->assertIsArray($recommendations);
        $this->assertArrayHasKey('enabled', $recommendations);
    }

    /** @test */
    public function it_supports_multiple_locales()
    {
        $locales = ['fr', 'en', 'es', 'pt'];

        foreach ($locales as $locale) {
            $recommendations = $this->service->generateRecommendations(
                tenantId: 1,
                module: 'Accounting',
                ratios: ['current_ratio' => ['value' => 1.2, 'benchmark' => 1.5]],
                locale: $locale
            );

            $this->assertIsArray($recommendations);
        }
    }

    /** @test */
    public function it_caches_recommendations()
    {
        $ratios = [
            'current_ratio' => ['value' => 1.2, 'benchmark' => 1.5, 'status' => 'red'],
        ];

        $rec1 = $this->service->generateRecommendations(
            tenantId: 1,
            module: 'Accounting',
            ratios: $ratios,
            locale: 'fr'
        );

        $rec2 = $this->service->generateRecommendations(
            tenantId: 1,
            module: 'Accounting',
            ratios: $ratios,
            locale: 'fr'
        );

        $this->assertEquals($rec1, $rec2);
    }

    /** @test */
    public function it_analyzes_correlation_patterns()
    {
        $correlations = [
            ['kpi1' => 'sales', 'kpi2' => 'customer_count', 'pearson' => 0.87],
            ['kpi1' => 'inventory', 'kpi2' => 'sales', 'pearson' => -0.45],
        ];

        $analysis = $this->service->analyzeCorrelationPatterns(
            tenantId: 1,
            correlations: $correlations,
            locale: 'fr'
        );

        $this->assertIsArray($analysis);
    }

    /** @test */
    public function it_generates_strategic_narrative()
    {
        $snapshot = [
            'module' => 'Accounting',
            'ratios' => [
                'current_ratio' => ['value' => 1.2, 'benchmark' => 1.5, 'status' => 'red'],
                'net_profit_margin' => ['value' => 8.5, 'benchmark' => 10, 'status' => 'yellow'],
            ],
            'trend' => 'declining',
        ];

        $narrative = $this->service->generateNarrative(
            tenantId: 1,
            snapshot: $snapshot,
            locale: 'fr'
        );

        $this->assertIsArray($narrative);
    }

    /** @test */
    public function it_identifies_action_items()
    {
        $ratios = [
            'current_ratio' => ['value' => 1.2, 'benchmark' => 1.5, 'status' => 'red', 'trend' => 'down'],
            'debt_to_equity' => ['value' => 1.2, 'benchmark' => 0.6, 'status' => 'red', 'trend' => 'up'],
        ];

        $actions = $this->service->identifyActionItems(
            tenantId: 1,
            module: 'Accounting',
            ratios: $ratios,
            locale: 'fr'
        );

        $this->assertIsArray($actions);
        if (!empty($actions)) {
            foreach ($actions as $action) {
                $this->assertArrayHasKey('priority', $action);
                $this->assertArrayHasKey('description', $action);
            }
        }
    }
}
