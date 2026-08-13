<?php

namespace Modules\BI\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Modules\BI\Services\AdvancedReportBuilderService;
use Modules\BI\Services\BIDashboardEngineService;
use Modules\BI\Services\AdvancedAnalyticsService;
use Modules\BI\Services\ReportSchedulingService;
use Tests\TestCase;

class AdvancedReportingTest extends TestCase
{
    protected AdvancedReportBuilderService $reportBuilder;
    protected BIDashboardEngineService $dashboardEngine;
    protected AdvancedAnalyticsService $analytics;
    protected ReportSchedulingService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportBuilder = app(AdvancedReportBuilderService::class);
        $this->dashboardEngine = app(BIDashboardEngineService::class);
        $this->analytics = app(AdvancedAnalyticsService::class);
        $this->scheduler = app(ReportSchedulingService::class);

        Cache::flush();
    }

    // ======================================================================
    // Report Builder Tests
    // ======================================================================

    /**
     * Test creating custom report
     */
    public function test_create_custom_report()
    {
        $config = [
            'name' => 'Sales Report',
            'data_source' => 'orders',
            'metrics' => ['sum(amount)', 'count(*)'],
            'dimensions' => ['date', 'product_id'],
            'filters' => [
                ['column' => 'status', 'operator' => 'equals', 'value' => 'completed'],
            ],
        ];

        $result = $this->reportBuilder->createReport($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('report_id', $result);
        $this->assertEquals('Sales Report', $result['config']['name']);
    }

    public function test_add_metric_to_report()
    {
        $config = [
            'name' => 'Sales Report',
            'data_source' => 'orders',
        ];

        $report = $this->reportBuilder->createReport($config);
        $reportId = $report['report_id'];

        $result = $this->reportBuilder->addMetric(
            $reportId,
            'total_revenue',
            'amount',
            'sum'
        );

        $this->assertEquals('added', $result['status']);
        $this->assertEquals('total_revenue', $result['metric']['name']);
    }

    public function test_add_filter_to_report()
    {
        $config = [
            'name' => 'Sales Report',
            'data_source' => 'orders',
        ];

        $report = $this->reportBuilder->createReport($config);
        $reportId = $report['report_id'];

        $filter = [
            'column' => 'status',
            'operator' => 'equals',
            'value' => 'completed',
        ];

        $result = $this->reportBuilder->addFilter($reportId, $filter);

        $this->assertEquals('added', $result['status']);
    }

    public function test_clone_report()
    {
        $config = [
            'name' => 'Sales Report',
            'data_source' => 'orders',
        ];

        $report = $this->reportBuilder->createReport($config);
        $reportId = $report['report_id'];

        $clone = $this->reportBuilder->cloneReport($reportId, 'Sales Report Clone');

        $this->assertEquals('cloned', $clone['status']);
        $this->assertNotEquals($reportId, $clone['new_report_id']);
    }

    public function test_delete_report()
    {
        $config = [
            'name' => 'Sales Report',
            'data_source' => 'orders',
        ];

        $report = $this->reportBuilder->createReport($config);
        $reportId = $report['report_id'];

        $result = $this->reportBuilder->deleteReport($reportId);

        $this->assertEquals('deleted', $result['status']);
        $this->assertNull($this->reportBuilder->getReport($reportId));
    }

    // ======================================================================
    // Dashboard Engine Tests
    // ======================================================================

    /**
     * Test creating dashboard
     */
    public function test_create_dashboard()
    {
        $config = [
            'name' => 'Executive Dashboard',
            'description' => 'Top KPIs for executives',
            'owner_id' => 1,
        ];

        $result = $this->dashboardEngine->createDashboard($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('dashboard_id', $result);
    }

    public function test_add_widget_to_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $widget = [
            'type' => 'chart',
            'title' => 'Revenue Trend',
            'report_id' => 'report_123',
            'position' => ['row' => 0, 'col' => 0],
            'size' => ['width' => 6, 'height' => 4],
        ];

        $result = $this->dashboardEngine->addWidget($dashboardId, $widget);

        $this->assertEquals('added', $result['status']);
        $this->assertArrayHasKey('widget_id', $result);
    }

    public function test_update_widget_config()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $widget = $this->dashboardEngine->addWidget($dashboardId, [
            'type' => 'chart',
            'title' => 'Revenue Trend',
            'report_id' => 'report_123',
        ]);

        $widgetId = $widget['widget_id'];

        $result = $this->dashboardEngine->updateWidget($dashboardId, $widgetId, [
            'title' => 'Updated Title',
            'type' => 'metric',
        ]);

        $this->assertEquals('updated', $result['status']);
    }

    public function test_remove_widget_from_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $widget = $this->dashboardEngine->addWidget($dashboardId, [
            'type' => 'chart',
            'title' => 'Revenue Trend',
            'report_id' => 'report_123',
        ]);

        $widgetId = $widget['widget_id'];

        $result = $this->dashboardEngine->removeWidget($dashboardId, $widgetId);

        $this->assertEquals('removed', $result['status']);
    }

    public function test_clone_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $clone = $this->dashboardEngine->cloneDashboard($dashboardId, 'Sales Dashboard');

        $this->assertEquals('cloned', $clone['status']);
        $this->assertNotEquals($dashboardId, $clone['new_dashboard_id']);
    }

    public function test_share_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $result = $this->dashboardEngine->shareDashboard(
            $dashboardId,
            [2, 3, 4],
            'view'
        );

        $this->assertEquals('shared', $result['status']);
        $this->assertEquals(3, $result['shared_with']);
    }

    public function test_toggle_favorite_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $result = $this->dashboardEngine->toggleFavorite($dashboardId, 1);

        $this->assertEquals('added', $result['status']);

        $result = $this->dashboardEngine->toggleFavorite($dashboardId, 1);

        $this->assertEquals('removed', $result['status']);
    }

    /**
     * Test refresh dashboard
     */
    public function test_refresh_dashboard()
    {
        $dashboard = $this->dashboardEngine->createDashboard([
            'name' => 'Executive Dashboard',
            'owner_id' => 1,
        ]);

        $dashboardId = $dashboard['dashboard_id'];

        $result = $this->dashboardEngine->refreshDashboard($dashboardId);

        $this->assertEquals('refreshed', $result['status']);
    }

    // ======================================================================
    // Analytics Tests
    // ======================================================================

    /**
     * Test trend analysis
     */
    public function test_analyze_trend()
    {
        $data = [
            ['date' => '2026-01-01', 'value' => 100],
            ['date' => '2026-01-02', 'value' => 120],
            ['date' => '2026-01-03', 'value' => 150],
            ['date' => '2026-01-04', 'value' => 180],
            ['date' => '2026-01-05', 'value' => 200],
        ];

        $result = $this->analytics->analyzeTrend($data, 'date', 'value');

        $this->assertArrayHasKey('trend', $result);
        $this->assertArrayHasKey('volatility', $result);
        $this->assertArrayHasKey('momentum', $result);
        $this->assertArrayHasKey('forecast', $result);
        $this->assertEquals('upward', $result['trend']['direction']);
    }

    /**
     * Test cohort analysis
     */
    public function test_cohort_analysis()
    {
        $data = [
            ['segment' => 'A', 'date' => '2026-01-01', 'value' => 100],
            ['segment' => 'A', 'date' => '2026-01-02', 'value' => 120],
            ['segment' => 'B', 'date' => '2026-01-01', 'value' => 150],
            ['segment' => 'B', 'date' => '2026-01-02', 'value' => 180],
        ];

        $result = $this->analytics->analyzeCohorts($data, 'segment', 'date', 'value');

        $this->assertArrayHasKey('A', $result);
        $this->assertArrayHasKey('B', $result);
        $this->assertGreaterThan(0, count($result['A']));
    }

    /**
     * Test retention analysis
     */
    public function test_retention_analysis()
    {
        $data = [
            ['user_id' => 1, 'date' => '2026-01-01'],
            ['user_id' => 1, 'date' => '2026-01-05'],
            ['user_id' => 1, 'date' => '2026-01-10'],
            ['user_id' => 2, 'date' => '2026-01-02'],
        ];

        $result = $this->analytics->analyzeRetention($data, 'user_id', 'date');

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey('retention_30d', $result[1]);
    }

    /**
     * Test LTV estimation
     */
    public function test_estimate_ltv()
    {
        $transactions = [
            ['user_id' => 1, 'amount' => 100],
            ['user_id' => 1, 'amount' => 150],
            ['user_id' => 2, 'amount' => 200],
        ];

        $result = $this->analytics->estimateLTV($transactions, 'user_id', 'amount');

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey('estimated_ltv', $result[1]);
    }

    // ======================================================================
    // Scheduling Tests
    // ======================================================================

    /**
     * Test schedule report
     */
    public function test_schedule_report()
    {
        $config = [
            'report_id' => 'report_123',
            'frequency' => 'daily',
            'time' => '09:00',
            'recipients' => ['user@example.com'],
            'format' => 'pdf',
        ];

        $result = $this->scheduler->scheduleReport($config);

        $this->assertEquals('created', $result['status']);
        $this->assertArrayHasKey('schedule_id', $result);
        $this->assertArrayHasKey('next_run', $result);
    }

    public function test_update_schedule()
    {
        $schedule = $this->scheduler->scheduleReport([
            'report_id' => 'report_123',
            'frequency' => 'daily',
            'time' => '09:00',
        ]);

        $scheduleId = $schedule['schedule_id'];

        $result = $this->scheduler->updateSchedule($scheduleId, [
            'frequency' => 'weekly',
            'time' => '10:00',
        ]);

        $this->assertEquals('updated', $result['status']);
    }

    public function test_delete_schedule()
    {
        $schedule = $this->scheduler->scheduleReport([
            'report_id' => 'report_123',
            'frequency' => 'daily',
            'time' => '09:00',
        ]);

        $scheduleId = $schedule['schedule_id'];

        $result = $this->scheduler->deleteSchedule($scheduleId);

        $this->assertEquals('deleted', $result['status']);
        $this->assertNull($this->scheduler->getSchedule($scheduleId));
    }

    /**
     * Test export report
     */
    public function test_export_report_csv()
    {
        $data = [
            ['id' => 1, 'name' => 'Product A', 'sales' => 100],
            ['id' => 2, 'name' => 'Product B', 'sales' => 150],
        ];

        $result = $this->scheduler->exportReport($data, 'csv');

        $this->assertEquals('completed', $result['status']);
        $this->assertArrayHasKey('file_name', $result);
    }

    public function test_export_report_json()
    {
        $data = [
            ['id' => 1, 'name' => 'Product A'],
        ];

        $result = $this->scheduler->exportReport($data, 'json');

        $this->assertEquals('completed', $result['status']);
    }
}
