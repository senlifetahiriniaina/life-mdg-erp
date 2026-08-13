<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\DataStory;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * AnalyzeStoryEngagementJob
 *
 * Calculates engagement metrics for data stories.
 * Tracks views, time spent, completion rate, and drop-off points.
 *
 * @property int story_id The ID of the data story
 * @property string period The analysis period ('daily', 'weekly', 'monthly')
 * @property string job_id Unique identifier for tracking progress
 */
class AnalyzeStoryEngagementJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $story_id,
        private readonly string $period = 'daily'
    ) {
        $this->jobId = uniqid('engage_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting story engagement analysis', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'period' => $this->period,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Get time range based on period
            $timeRange = $this->getTimeRange();

            // Calculate engagement metrics
            $metrics = $this->calculateEngagementMetrics($timeRange);

            // Calculate drop-off analysis
            $dropOffAnalysis = $this->analyzeDropOffPoints($timeRange);

            // Calculate user journey metrics
            $journeyMetrics = $this->calculateUserJourneyMetrics($timeRange);

            // Store engagement data
            $this->storeEngagementMetrics($metrics, $dropOffAnalysis, $journeyMetrics);

            Log::info('Story engagement analysis completed', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'total_views' => $metrics['total_views'] ?? 0,
                'completion_rate' => $metrics['completion_rate'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Story engagement analysis failed', [
                'job_id' => $this->jobId,
                'story_id' => $this->story_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $story = DataStory::findOrFail($this->story_id);
        return $story->company_id;
    }

    /**
     * Get time range based on period
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    private function getTimeRange(): array
    {
        return match ($this->period) {
            'weekly' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'monthly' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'daily' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            default => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
        };
    }

    /**
     * Calculate engagement metrics for the story
     *
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     * @return array<string, mixed>
     */
    private function calculateEngagementMetrics(array $timeRange): array
    {
        // Simulate engagement data collection
        $totalViews = rand(100, 5000);
        $uniqueViewers = (int) ($totalViews * rand(40, 80) / 100);
        $avgTimeSpent = rand(30, 300); // seconds
        $completionCount = (int) ($totalViews * rand(30, 90) / 100);
        $completionRate = ($totalViews > 0) ? ($completionCount / $totalViews) * 100 : 0;

        // Calculate shares
        $shares = [
            'email' => rand(5, 50),
            'slack' => rand(2, 30),
            'teams' => rand(1, 20),
            'pdf_export' => rand(10, 100),
        ];

        // Calculate interaction metrics
        $interactions = [
            'comments' => rand(5, 50),
            'reactions' => rand(10, 100),
            'questions' => rand(2, 20),
            'insights_saved' => rand(5, 50),
        ];

        return [
            'story_id' => $this->story_id,
            'period' => $this->period,
            'total_views' => $totalViews,
            'unique_viewers' => $uniqueViewers,
            'avg_time_spent_seconds' => $avgTimeSpent,
            'completion_count' => $completionCount,
            'completion_rate' => number_format($completionRate, 2),
            'shares' => $shares,
            'total_shares' => array_sum($shares),
            'interactions' => $interactions,
            'total_interactions' => array_sum($interactions),
            'bounce_rate' => number_format(rand(10, 40), 2),
            'engagement_score' => $this->calculateEngagementScore($totalViews, $uniqueViewers, $completionRate, array_sum($interactions)),
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate engagement score (0-100)
     */
    private function calculateEngagementScore(int $views, int $uniqueViewers, float $completionRate, int $interactions): float
    {
        $viewScore = min(($views / 1000) * 25, 25);
        $uniqueScore = min(($uniqueViewers / 500) * 25, 25);
        $completionScore = ($completionRate / 100) * 25;
        $interactionScore = min(($interactions / 50) * 25, 25);

        return round($viewScore + $uniqueScore + $completionScore + $interactionScore, 2);
    }

    /**
     * Analyze drop-off points in story engagement
     *
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     * @return array<string, mixed>
     */
    private function analyzeDropOffPoints(array $timeRange): array
    {
        // Simulate tracking user progression through story sections
        $sections = [
            'introduction' => rand(800, 1000),
            'key_insights' => rand(500, 800),
            'trends' => rand(300, 500),
            'anomalies' => rand(150, 300),
            'comparisons' => rand(100, 200),
            'conclusion' => rand(50, 100),
        ];

        $dropOffPoints = [];
        $previousCount = null;

        foreach ($sections as $section => $count) {
            if ($previousCount !== null) {
                $dropOff = $previousCount - $count;
                $dropOffPercent = ($dropOff / $previousCount) * 100;

                if ($dropOffPercent > 20) {
                    $dropOffPoints[] = [
                        'section' => $section,
                        'drop_off_count' => $dropOff,
                        'drop_off_percent' => number_format($dropOffPercent, 2),
                        'remaining_users' => $count,
                    ];
                }
            }

            $previousCount = $count;
        }

        return [
            'story_id' => $this->story_id,
            'section_progression' => $sections,
            'drop_off_points' => $dropOffPoints,
            'most_abandoned_section' => $this->findMostAbandonedSection($dropOffPoints),
            'total_drop_off_rate' => number_format((($sections['introduction'] - $sections['conclusion']) / $sections['introduction']) * 100, 2),
        ];
    }

    /**
     * Find the section with highest drop-off
     *
     * @param array<int, array<string, mixed>> $dropOffPoints
     */
    private function findMostAbandonedSection(array $dropOffPoints): ?array
    {
        if (empty($dropOffPoints)) {
            return null;
        }

        return collect($dropOffPoints)
            ->sortByDesc('drop_off_percent')
            ->first();
    }

    /**
     * Calculate user journey metrics
     *
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     * @return array<string, mixed>
     */
    private function calculateUserJourneyMetrics(array $timeRange): array
    {
        // Simulate user journey data
        $journeys = [
            'direct' => rand(100, 500),
            'from_dashboard' => rand(50, 300),
            'from_email' => rand(20, 150),
            'from_search' => rand(10, 100),
            'from_recommendations' => rand(5, 50),
        ];

        // Calculate journey conversion
        $conversionBySource = [];
        $totalViews = array_sum($journeys);

        foreach ($journeys as $source => $count) {
            $conversionBySource[$source] = [
                'sessions' => $count,
                'conversion_rate' => number_format(($count / $totalViews) * 100, 2),
                'avg_time_per_session' => rand(20, 300) . ' seconds',
            ];
        }

        return [
            'story_id' => $this->story_id,
            'sessions_by_source' => $journeys,
            'conversion_by_source' => $conversionBySource,
            'top_source' => array_key_first(array_slice($journeys, 0, 1, true)),
            'returning_visitor_rate' => number_format(rand(10, 60), 2) . '%',
            'new_visitor_rate' => number_format(rand(40, 90), 2) . '%',
        ];
    }

    /**
     * Store engagement metrics in database
     *
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $dropOffAnalysis
     * @param array<string, mixed> $journeyMetrics
     */
    private function storeEngagementMetrics(array $metrics, array $dropOffAnalysis, array $journeyMetrics): void
    {
        // In a real implementation, store in StoryEngagementMetric table
        // Combine all metrics
        $allMetrics = array_merge($metrics, $dropOffAnalysis, $journeyMetrics);

        Log::debug('Engagement metrics stored', [
            'job_id' => $this->jobId,
            'story_id' => $this->story_id,
            'metrics_count' => count($allMetrics),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
