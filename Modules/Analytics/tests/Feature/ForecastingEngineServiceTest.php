<?php

namespace Modules\Analytics\Tests\Feature;

use Tests\TestCase;
use Modules\Analytics\Services\ForecastingEngineService;

class ForecastingEngineServiceTest extends TestCase
{
    private ForecastingEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ForecastingEngineService::class);
    }

    /** @test */
    public function it_generates_demand_forecast()
    {
        $historicalData = [
            ['date' => '2026-01-01', 'value' => 100],
            ['date' => '2026-02-01', 'value' => 120],
            ['date' => '2026-03-01', 'value' => 110],
            ['date' => '2026-04-01', 'value' => 130],
            ['date' => '2026-05-01', 'value' => 125],
        ];

        $forecast = $this->service->forecast('demand', $historicalData, periods: 3);

        $this->assertIsArray($forecast);
        $this->assertArrayHasKey('values', $forecast);
        $this->assertArrayHasKey('algorithm', $forecast);
        $this->assertEquals(3, count($forecast['values']));
    }

    /** @test */
    public function it_uses_linear_regression_algorithm()
    {
        $data = [
            ['value' => 100], ['value' => 110], ['value' => 120],
            ['value' => 130], ['value' => 140],
        ];

        $forecast = $this->service->forecast('demand', $data, algorithm: 'linear_regression', periods: 2);

        $this->assertIn('linear_regression', $forecast['algorithm']);
    }

    /** @test */
    public function it_uses_moving_average_algorithm()
    {
        $data = [
            ['value' => 100], ['value' => 105], ['value' => 110],
            ['value' => 115], ['value' => 120],
        ];

        $forecast = $this->service->forecast('demand', $data, algorithm: 'moving_average', periods: 2, window: 3);

        $this->assertIn('moving_average', $forecast['algorithm']);
    }

    /** @test */
    public function it_uses_exponential_smoothing()
    {
        $data = [
            ['value' => 100], ['value' => 105], ['value' => 103],
            ['value' => 108], ['value' => 112],
        ];

        $forecast = $this->service->forecast('demand', $data, algorithm: 'exponential_smoothing', periods: 2);

        $this->assertIn('exponential_smoothing', $forecast['algorithm']);
    }

    /** @test */
    public function it_generates_cashflow_forecast()
    {
        $data = [
            ['date' => '2026-05-01', 'inflow' => 50000, 'outflow' => 30000],
            ['date' => '2026-05-08', 'inflow' => 60000, 'outflow' => 35000],
            ['date' => '2026-05-15', 'inflow' => 55000, 'outflow' => 32000],
        ];

        $forecast = $this->service->forecastCashflow($data, days: 30);

        $this->assertIsArray($forecast);
        $this->assertArrayHasKey('daily_balance', $forecast);
        $this->assertGreaterThan(0, count($forecast['daily_balance']));
    }

    /** @test */
    public function it_detects_stockout_risk()
    {
        $demand = [100, 110, 120, 115, 125, 130];
        $stock = 200;

        $risk = $this->service->detectStockoutRisk('product_123', $demand, $stock);

        $this->assertIsArray($risk);
        $this->assertArrayHasKey('risk_level', $risk);
        $this->assertIn($risk['risk_level'], ['low', 'medium', 'high', 'critical']);
    }

    /** @test */
    public function it_calculates_reorder_point()
    {
        $avgDailyDemand = 10;
        $leadTime = 7;
        $safetyStock = 5;

        $rop = $this->service->calculateReorderPoint($avgDailyDemand, $leadTime, $safetyStock);

        $this->assertIsFloat($rop);
        $this->assertGreaterThan($safetyStock, $rop);
    }

    /** @test */
    public function it_scenarios_multiple_forecasts()
    {
        $data = [
            ['value' => 100], ['value' => 105], ['value' => 110],
        ];

        $scenarios = $this->service->generateScenarios($data, scenarios: ['optimistic', 'pessimistic', 'most_likely']);

        $this->assertIsArray($scenarios);
        $this->assertArrayHasKey('optimistic', $scenarios);
        $this->assertArrayHasKey('pessimistic', $scenarios);
        $this->assertArrayHasKey('most_likely', $scenarios);
    }

    /** @test */
    public function it_computes_forecast_accuracy()
    {
        $actual = [100, 110, 120, 125];
        $predicted = [98, 112, 118, 128];

        $mae = $this->service->computeMeanAbsoluteError($actual, $predicted);

        $this->assertIsFloat($mae);
        $this->assertGreaterThanOrEqual(0, $mae);
    }

    /** @test */
    public function it_handles_seasonal_variations()
    {
        $data = array_map(function($i) {
            return ['value' => 100 + 20 * sin($i * 0.3)];
        }, range(1, 12));

        $forecast = $this->service->forecast('demand', $data, algorithm: 'holt_winters', periods: 3);

        $this->assertIsArray($forecast);
        $this->assertArrayHasKey('values', $forecast);
    }

    /** @test */
    public function it_handles_insufficient_data()
    {
        $data = [['value' => 100]];

        $forecast = $this->service->forecast('demand', $data, periods: 2);

        $this->assertIsArray($forecast);
    }

    /** @test */
    public function it_validates_forecast_output()
    {
        $data = [
            ['value' => 100], ['value' => 110], ['value' => 120],
            ['value' => 130], ['value' => 140],
        ];

        $forecast = $this->service->forecast('demand', $data, periods: 2);

        $this->assertIsArray($forecast['values']);
        foreach ($forecast['values'] as $value) {
            $this->assertIsFloat($value);
            $this->assertGreaterThan(0, $value);
        }
    }

    /** @test */
    public function it_forecasts_hr_headcount()
    {
        $historicalHeadcount = [50, 52, 55, 58, 61];
        $turnoverRate = 0.15;

        $forecast = $this->service->forecastHrHeadcount($historicalHeadcount, $turnoverRate, quarters: 4);

        $this->assertIsArray($forecast);
    }

    /** @test */
    public function it_identifies_demand_surge()
    {
        $demand = [100, 100, 100, 105, 110, 140, 135, 130];

        $surges = $this->service->identifyDemandSurges($demand);

        $this->assertIsArray($surges);
    }
}
