<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\StrategyRatioDefinition;
use Modules\Strategy\Services\StrategyRatioService;
use Modules\Strategy\Services\KPIRegistryService;

class StrategyRatioServiceTest extends TestCase
{
    private StrategyRatioService $service;
    private KPIRegistryService $kpiRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StrategyRatioService::class);
        $this->kpiRegistry = app(KPIRegistryService::class);
    }

    /** @test */
    public function it_calculates_ratio_from_two_kpis()
    {
        $numerator = $this->kpiRegistry->getValue('Accounting', 'net_profit_margin');
        $denominator = 100;

        $ratio = $this->service->calculate('Accounting', 'net_profit_margin', $denominator);

        $this->assertIsFloat($ratio);
        $this->assertGreaterThanOrEqual(0, $ratio);
    }

    /** @test */
    public function it_computes_rag_status_for_ratio()
    {
        $status = $this->service->computeRagStatus(
            ratio: 1.85,
            benchmark: 1.5,
            direction: 'up',
            tolerance: 0.1
        );

        $this->assertIn($status, ['green', 'yellow', 'red']);
    }

    /** @test */
    public function it_returns_green_status_when_ratio_exceeds_benchmark()
    {
        $status = $this->service->computeRagStatus(
            ratio: 2.0,
            benchmark: 1.5,
            direction: 'up',
            tolerance: 0.1
        );

        $this->assertEquals('green', $status);
    }

    /** @test */
    public function it_returns_yellow_status_when_ratio_near_benchmark()
    {
        $status = $this->service->computeRagStatus(
            ratio: 1.52,
            benchmark: 1.5,
            direction: 'up',
            tolerance: 0.1
        );

        $this->assertEquals('yellow', $status);
    }

    /** @test */
    public function it_returns_red_status_when_ratio_below_benchmark()
    {
        $status = $this->service->computeRagStatus(
            ratio: 1.0,
            benchmark: 1.5,
            direction: 'up',
            tolerance: 0.1
        );

        $this->assertEquals('red', $status);
    }

    /** @test */
    public function it_handles_down_direction_correctly()
    {
        $statusGood = $this->service->computeRagStatus(
            ratio: 0.5,
            benchmark: 1.0,
            direction: 'down',
            tolerance: 0.1
        );

        $this->assertEquals('green', $statusGood);

        $statusBad = $this->service->computeRagStatus(
            ratio: 2.0,
            benchmark: 1.0,
            direction: 'down',
            tolerance: 0.1
        );

        $this->assertEquals('red', $statusBad);
    }

    /** @test */
    public function it_prevents_division_by_zero()
    {
        $ratio = $this->service->calculate('Accounting', 'current_ratio', 0);

        $this->assertIsFloat($ratio);
        $this->assertEquals(0.0, $ratio);
    }

    /** @test */
    public function it_stores_ratio_snapshot()
    {
        $snapshot = $this->service->storeSnapshot(
            tenantId: 1,
            module: 'Accounting',
            ratioKey: 'current_ratio',
            value: 1.75,
            benchmark: 1.5,
            status: 'green'
        );

        $this->assertNotNull($snapshot);
        $this->assertEquals('Accounting', $snapshot->module);
        $this->assertEquals('current_ratio', $snapshot->ratio_key);
        $this->assertEquals(1.75, $snapshot->value);
    }

    /** @test */
    public function it_retrieves_ratio_history()
    {
        $tenantId = 1;

        $this->service->storeSnapshot($tenantId, 'Accounting', 'current_ratio', 1.7, 1.5, 'green');
        $this->service->storeSnapshot($tenantId, 'Accounting', 'current_ratio', 1.75, 1.5, 'green');
        $this->service->storeSnapshot($tenantId, 'Accounting', 'current_ratio', 1.8, 1.5, 'green');

        $history = $this->service->getHistory($tenantId, 'Accounting', 'current_ratio', limit: 10);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history));
    }

    /** @test */
    public function it_calculates_trend_from_ratio_history()
    {
        $values = [1.5, 1.6, 1.7, 1.8];
        $trend = $this->service->calculateTrend($values);

        $this->assertIsString($trend);
        $this->assertIn($trend, ['up', 'down', 'stable']);
    }
}
