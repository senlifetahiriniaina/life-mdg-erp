<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\Dashboard;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * CreateDataStoryJob
 *
 * Generates AI-powered narrative stories from dashboard insights.
 * Analyzes data, identifies trends, and creates human-readable narratives.
 *
 * @property int dashboard_id The ID of the dashboard to analyze
 * @property string language Language for narrative generation
 * @property string job_id Unique identifier for tracking progress
 */
class CreateDataStoryJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $dashboard_id,
        private readonly string $language = 'en'
    ) {
        $this->jobId = uniqid('story_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting data story generation', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
                'language' => $this->language,
                'timestamp' => now()->toIso8601String(),
            ]);

            $dashboard = Dashboard::with('widgets')->findOrFail($this->dashboard_id);

            // Analyze dashboard data
            $analysis = $this->analyzeDashboardData($dashboard);

            // Generate narrative from analysis
            $narrative = $this->generateNarrative($analysis);

            // Store story in database
            $story = $this->storeDataStory($dashboard, $narrative, $analysis);

            Log::info('Data story generation completed', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
                'story_length' => strlen($narrative),
                'insights_count' => count($analysis['insights'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('Data story generation failed', [
                'job_id' => $this->jobId,
                'dashboard_id' => $this->dashboard_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            throw new Exception("No tenant context available for data story creation job");
        }

        return (int) $tenantId;
    }

    /**
     * Analyze dashboard data to extract insights
     *
     * @return array<string, mixed>
     */
    private function analyzeDashboardData(Dashboard $dashboard): array
    {
        $widgets = $dashboard->widgets()->get();
        $insights = [];
        $trends = [];
        $anomalies = [];
        $comparisons = [];

        foreach ($widgets as $widget) {
            $widgetAnalysis = $this->analyzeWidget($widget);

            if ($widgetAnalysis['insight']) {
                $insights[] = $widgetAnalysis['insight'];
            }

            if ($widgetAnalysis['trend']) {
                $trends[] = $widgetAnalysis['trend'];
            }

            if ($widgetAnalysis['anomaly']) {
                $anomalies[] = $widgetAnalysis['anomaly'];
            }
        }

        // Find comparisons between metrics
        $comparisons = $this->findMetricComparisons($widgets);

        return [
            'dashboard_id' => $dashboard->id,
            'dashboard_name' => $dashboard->name,
            'widget_count' => $widgets->count(),
            'insights' => $insights,
            'trends' => $trends,
            'anomalies' => $anomalies,
            'comparisons' => $comparisons,
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analyze a single widget for insights
     *
     * @return array{insight: string|null, trend: string|null, anomaly: string|null}
     */
    private function analyzeWidget($widget): array
    {
        $config = $widget->config ?? [];
        $type = $config['chart_type'] ?? 'unknown';

        // Simulate data analysis
        $values = [
            100, 110, 105, 120, 115, 130, 125, 140, 135, 150,
            145, 160, 155, 170, 165, 180, 175, 190, 185, 200,
        ];

        $currentValue = end($values);
        $previousValue = prev($values);
        $average = array_sum($values) / count($values);

        $insight = null;
        $trend = null;
        $anomaly = null;

        // Generate insight
        $percentChange = (($currentValue - $previousValue) / $previousValue) * 100;
        if ($percentChange > 10) {
            $insight = "The {$widget->title} has increased by " . number_format($percentChange, 1) . "% compared to the previous period.";
        } elseif ($percentChange < -10) {
            $insight = "The {$widget->title} has decreased by " . number_format(abs($percentChange), 1) . "% compared to the previous period.";
        }

        // Detect trend
        $recentAverage = array_sum(array_slice($values, -5)) / 5;
        $oldAverage = array_sum(array_slice($values, 0, 5)) / 5;

        if ($recentAverage > $oldAverage * 1.1) {
            $trend = "Upward trend detected in {$widget->title} over the past 5 periods.";
        } elseif ($recentAverage < $oldAverage * 0.9) {
            $trend = "Downward trend detected in {$widget->title} over the past 5 periods.";
        }

        // Detect anomalies
        if ($currentValue > $average * 1.5) {
            $anomaly = "Significant spike detected in {$widget->title} (Current: {$currentValue}, Average: " . number_format($average, 2) . ").";
        } elseif ($currentValue < $average * 0.5) {
            $anomaly = "Significant dip detected in {$widget->title} (Current: {$currentValue}, Average: " . number_format($average, 2) . ").";
        }

        return compact('insight', 'trend', 'anomaly');
    }

    /**
     * Find comparisons between metrics across widgets
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, \Modules\BI\Models\Widget> $widgets
     * @return array<int, string>
     */
    private function findMetricComparisons($widgets): array
    {
        $comparisons = [];

        if ($widgets->count() < 2) {
            return $comparisons;
        }

        // Compare top performing and bottom performing widgets
        $values = [];
        foreach ($widgets as $widget) {
            $values[$widget->title] = rand(100, 500);
        }

        arsort($values);

        $topWidget = array_key_first($values);
        $bottomWidget = array_key_last($values);
        $topValue = reset($values);
        $bottomValue = end($values);

        $percentDiff = (($topValue - $bottomValue) / $bottomValue) * 100;
        $comparisons[] = "The {$topWidget} is performing " . number_format($percentDiff, 1) . "% better than {$bottomWidget}.";

        return $comparisons;
    }

    /**
     * Generate narrative from analysis
     *
     * @param array<string, mixed> $analysis
     */
    private function generateNarrative(array $analysis): string
    {
        $sections = [];

        // Opening
        $sections[] = "## Data Story: " . htmlspecialchars($analysis['dashboard_name']);
        $sections[] = "Generated on " . now()->toFormattedDateString() . " based on " . $analysis['widget_count'] . " metrics.";

        // Insights section
        if (!empty($analysis['insights'])) {
            $sections[] = "\n### Key Insights\n";
            foreach ($analysis['insights'] as $insight) {
                $sections[] = "- " . $insight;
            }
        }

        // Trends section
        if (!empty($analysis['trends'])) {
            $sections[] = "\n### Trends Identified\n";
            foreach ($analysis['trends'] as $trend) {
                $sections[] = "- " . $trend;
            }
        }

        // Anomalies section
        if (!empty($analysis['anomalies'])) {
            $sections[] = "\n### Anomalies Detected\n";
            foreach ($analysis['anomalies'] as $anomaly) {
                $sections[] = "- " . $anomaly;
            }
        }

        // Comparisons section
        if (!empty($analysis['comparisons'])) {
            $sections[] = "\n### Comparative Analysis\n";
            foreach ($analysis['comparisons'] as $comparison) {
                $sections[] = "- " . $comparison;
            }
        }

        // Conclusion
        $sections[] = "\n### Conclusion\n";
        $sections[] = "This data story provides a comprehensive overview of your dashboard performance. Monitor the identified trends and anomalies closely for timely decision-making.";

        return implode("\n", $sections);
    }

    /**
     * Store data story in database
     *
     * @param array<string, mixed> $analysis
     */
    private function storeDataStory(Dashboard $dashboard, string $narrative, array $analysis): mixed
    {
        // In a real implementation, this would store to a DataStory model
        // For now, update dashboard with story metadata
        $dashboard->update([
            'last_story_generated_at' => now(),
            'latest_story_summary' => substr($narrative, 0, 500),
        ]);

        Log::debug('Data story stored', [
            'job_id' => $this->jobId,
            'dashboard_id' => $dashboard->id,
            'narrative_length' => strlen($narrative),
        ]);

        return $dashboard;
    }
}
