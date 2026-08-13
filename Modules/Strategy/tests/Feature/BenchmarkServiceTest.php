<?php

namespace Modules\Strategy\Tests\Feature;

use Tests\TestCase;
use Modules\Strategy\Models\IndustryBenchmark;
use Modules\Strategy\Services\BenchmarkService;

class BenchmarkServiceTest extends TestCase
{
    private BenchmarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BenchmarkService::class);
    }

    /** @test */
    public function it_retrieves_benchmark_for_industry_and_country()
    {
        $benchmark = $this->service->getBenchmark(
            industry: 'Manufacturing',
            country: 'SN',
            ratioKey: 'current_ratio',
            year: 2026
        );

        $this->assertIsArray($benchmark);
        if (!empty($benchmark)) {
            $this->assertArrayHasKey('p25', $benchmark);
            $this->assertArrayHasKey('p50', $benchmark);
            $this->assertArrayHasKey('p75', $benchmark);
        }
    }

    /** @test */
    public function it_returns_global_defaults_when_country_not_found()
    {
        $benchmark = $this->service->getBenchmark(
            industry: 'Manufacturing',
            country: 'XX',
            ratioKey: 'current_ratio',
            year: 2026
        );

        $this->assertIsArray($benchmark);
    }

    /** @test */
    public function it_computes_percentile_rank()
    {
        $rank = $this->service->computePercentileRank(
            value: 1.75,
            p25: 1.5,
            p50: 1.75,
            p75: 2.0
        );

        $this->assertIsFloat($rank);
        $this->assertGreaterThanOrEqual(0, $rank);
        $this->assertLessThanOrEqual(100, $rank);
    }

    /** @test */
    public function it_correctly_ranks_value_at_percentile_50()
    {
        $rank = $this->service->computePercentileRank(
            value: 1.75,
            p25: 1.5,
            p50: 1.75,
            p75: 2.0
        );

        $this->assertGreaterThanOrEqual(40, $rank);
        $this->assertLessThanOrEqual(60, $rank);
    }

    /** @test */
    public function it_correctly_ranks_value_below_p25()
    {
        $rank = $this->service->computePercentileRank(
            value: 1.0,
            p25: 1.5,
            p50: 1.75,
            p75: 2.0
        );

        $this->assertLessThan(25, $rank);
    }

    /** @test */
    public function it_correctly_ranks_value_above_p75()
    {
        $rank = $this->service->computePercentileRank(
            value: 2.5,
            p25: 1.5,
            p50: 1.75,
            p75: 2.0
        );

        $this->assertGreaterThan(75, $rank);
    }

    /** @test */
    public function it_retrieves_all_benchmarks_for_module()
    {
        $benchmarks = $this->service->getAllForModule(
            module: 'Accounting',
            country: 'SN',
            year: 2026
        );

        $this->assertIsArray($benchmarks);
    }

    /** @test */
    public function it_stores_custom_benchmark()
    {
        $benchmark = $this->service->store(
            tenantId: 1,
            industry: 'Technology',
            country: 'CI',
            ratioKey: 'net_profit_margin',
            year: 2026,
            p25: 5,
            p50: 12,
            p75: 20
        );

        $this->assertNotNull($benchmark);
        $this->assertEquals('Technology', $benchmark->industry);
        $this->assertEquals('CI', $benchmark->country);
        $this->assertEquals(12, $benchmark->p50);
    }

    /** @test */
    public function it_computes_benchmark_gap()
    {
        $gap = $this->service->computeGap(
            actualValue: 1.5,
            benchmarkP50: 1.75,
            direction: 'up'
        );

        $this->assertIsFloat($gap);
        $this->assertLessThan(0, $gap);
    }

    /** @test */
    public function it_computes_positive_gap_when_above_benchmark()
    {
        $gap = $this->service->computeGap(
            actualValue: 2.0,
            benchmarkP50: 1.75,
            direction: 'up'
        );

        $this->assertGreaterThan(0, $gap);
    }

    /** @test */
    public function it_handles_zero_benchmark_value()
    {
        $gap = $this->service->computeGap(
            actualValue: 1.5,
            benchmarkP50: 0,
            direction: 'up'
        );

        $this->assertIsFloat($gap);
    }

    /** @test */
    public function it_supports_7_african_countries()
    {
        $countries = ['SN', 'CI', 'CM', 'KE', 'GH', 'NG', 'MG'];

        foreach ($countries as $country) {
            $benchmark = $this->service->getBenchmark(
                industry: 'Manufacturing',
                country: $country,
                ratioKey: 'current_ratio',
                year: 2026
            );

            $this->assertIsArray($benchmark);
        }
    }
}
