<?php

namespace Modules\BI\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\BI\Models\{Report, DataSource};
use Modules\BI\Services\BIService;

class BIReportingTest extends TestCase
{
    use RefreshDatabase;

    private BIService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BIService();
    }

    public function test_create_report(): void
    {
        $report = $this->service->createReport('Sales Report', 'table', ['source' => 'sales_data']);
        $this->assertNotNull($report->id);
    }

    public function test_execute_report_query(): void
    {
        $report = Report::factory()->create();
        $results = $this->service->executeReport($report);

        $this->assertIsArray($results);
    }

    public function test_report_caching_improves_performance(): void
    {
        $report = Report::factory()->create();

        $start1 = microtime(true);
        $this->service->executeReport($report);
        $time1 = microtime(true) - $start1;

        $start2 = microtime(true);
        $this->service->executeReport($report); // Should be cached
        $time2 = microtime(true) - $start2;

        $this->assertLessThan($time1, $time2 * 10); // Cached should be significantly faster
    }

    public function test_report_export_to_csv(): void
    {
        $report = Report::factory()->create();
        $csv = $this->service->exportReport($report, 'csv');

        $this->assertStringContainsString(',', $csv); // CSV format
    }

    public function test_report_export_to_pdf(): void
    {
        $report = Report::factory()->create();
        $pdf = $this->service->exportReport($report, 'pdf');

        $this->assertNotEmpty($pdf);
    }

    public function test_scheduled_report_execution(): void
    {
        $report = Report::factory()->create(['scheduled' => true, 'schedule_time' => now()]);

        $executed = $this->service->executeScheduledReports();
        $this->assertTrue($executed);
    }

    public function test_report_data_accuracy(): void
    {
        // Create known data
        $data = [['id' => 1, 'value' => 100], ['id' => 2, 'value' => 200]];
        $report = Report::factory()->create();

        $results = $this->service->executeReport($report);

        $this->assertEquals(300, array_sum(array_column($results, 'value')));
    }

    public function test_api_get_report(): void
    {
        $report = Report::factory()->create();

        $response = $this->getJson("/api/v1/bi/reports/{$report->id}");

        $response->assertStatus(200);
    }
}
