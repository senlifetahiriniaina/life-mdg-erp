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
        $benchmark = $this->service->getForRatio(
            ratioKey: 'current_ratio',
            tenantId: 'default',
            country: 'SN',
            industry: 'Manufacturing'
        );

        $this->assertIsArray($benchmark);
        $this->assertArrayHasKey('p25', $benchmark);
        $this->assertArrayHasKey('median', $benchmark);
        $this->assertArrayHasKey('p75', $benchmark);
        $this->assertArrayHasKey('source', $benchmark);
    }

    /** @test */
    public function it_returns_global_defaults_when_country_not_found()
    {
        // No DB row exists for country 'XX'/industry 'Manufacturing', and it
        // doesn't match the 'WW'/'general' fallback row either, so getForRatio()
        // falls through to the built-in DEFAULTS table for 'current_ratio'.
        $benchmark = $this->service->getForRatio(
            ratioKey: 'current_ratio',
            tenantId: 'default',
            country: 'XX',
            industry: 'Manufacturing'
        );

        $this->assertIsArray($benchmark);
        $this->assertEquals(1.1, $benchmark['p25']);
        $this->assertEquals(1.5, $benchmark['median']);
        $this->assertEquals(2.2, $benchmark['p75']);
        $this->assertEquals('WideHalo Global Benchmark 2026', $benchmark['source']);
    }

    /** @test */
    public function it_computes_percentile_rank()
    {
        $rank = $this->service->percentileRank(
            value: 1.75,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'up'
        );

        $this->assertIsInt($rank);
        $this->assertGreaterThanOrEqual(25, $rank);
        $this->assertLessThanOrEqual(75, $rank);
    }

    /** @test */
    public function it_correctly_ranks_value_at_midpoint()
    {
        $rank = $this->service->percentileRank(
            value: 1.75,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'up'
        );

        // (1.75 - 1.5) / (2.0 - 1.5) * 50 + 25 = 50
        $this->assertEquals(50, $rank);
    }

    /** @test */
    public function it_clips_value_at_or_below_p25_to_rank_25()
    {
        $rank = $this->service->percentileRank(
            value: 1.0,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'up'
        );

        $this->assertEquals(25, $rank);
    }

    /** @test */
    public function it_clips_value_at_or_above_p75_to_rank_75()
    {
        $rank = $this->service->percentileRank(
            value: 2.5,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'up'
        );

        $this->assertEquals(75, $rank);
    }

    /** @test */
    public function it_inverts_percentile_rank_for_down_direction_metrics()
    {
        // For "down" metrics (lower is better), a high value ranks low.
        $rankAtHighValue = $this->service->percentileRank(
            value: 2.5,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'down'
        );
        $rankAtLowValue = $this->service->percentileRank(
            value: 1.0,
            benchmarkData: ['p25' => 1.5, 'p75' => 2.0],
            direction: 'down'
        );

        $this->assertEquals(25, $rankAtHighValue);
        $this->assertEquals(75, $rankAtLowValue);
    }

    /** @test */
    public function it_returns_null_percentile_rank_when_benchmark_bounds_missing()
    {
        $rank = $this->service->percentileRank(
            value: 1.75,
            benchmarkData: ['p25' => null, 'p75' => null],
            direction: 'up'
        );

        $this->assertNull($rank);
    }

    /** @test */
    public function it_lists_all_benchmarks()
    {
        $benchmarks = $this->service->listAll(country: 'SN', year: 2026);

        $this->assertIsArray($benchmarks);
        // No DB rows are seeded for SN/2026 in this test's DB, so listAll()
        // falls back to the built-in defaults exposed as structured rows.
        $this->assertNotEmpty($benchmarks);
    }

    /** @test */
    public function it_stores_and_retrieves_a_custom_benchmark()
    {
        // BenchmarkService has no store() method of its own — custom
        // benchmarks are written directly against the IndustryBenchmark
        // model (this is how the row would land in strategy_industry_benchmarks
        // in real usage), then read back through getForRatio().
        $benchmark = IndustryBenchmark::create([
            'ratio_name' => 'net_profit_margin',
            'industry'   => 'Technology',
            'country'    => 'CI',
            'p25'        => 5,
            'median'     => 12,
            'p75'        => 20,
            'year'       => 2026,
            'source'     => 'Custom',
        ]);

        $this->assertNotNull($benchmark);
        $this->assertEquals('Technology', $benchmark->industry);
        $this->assertEquals('CI', $benchmark->country);
        $this->assertEquals(12, $benchmark->median);

        $retrieved = $this->service->getForRatio(
            ratioKey: 'net_profit_margin',
            tenantId: 'default',
            country: 'CI',
            industry: 'Technology'
        );

        $this->assertEquals(12.0, $retrieved['median']);
        $this->assertEquals('Custom', $retrieved['source']);
    }

    /** @test */
    public function it_supports_7_african_countries()
    {
        $countries = ['SN', 'CI', 'CM', 'KE', 'GH', 'NG', 'MG'];

        foreach ($countries as $country) {
            $benchmark = $this->service->getForRatio(
                ratioKey: 'current_ratio',
                tenantId: 'default',
                country: $country,
                industry: 'Manufacturing'
            );

            $this->assertIsArray($benchmark);
            $this->assertArrayHasKey('p25', $benchmark);
        }
    }
}
