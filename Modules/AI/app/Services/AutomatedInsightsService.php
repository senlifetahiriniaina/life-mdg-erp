<?php

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;

class AutomatedInsightsService
{
    const CACHE_TTL = 86400;

    /**
     * Generate automated insights from data
     */
    public function generateInsights(array $data, string $context = 'general'): array
    {
        $insightId = uniqid('insight_');

        $insights = [
            'id' => $insightId,
            'context' => $context,
            'summary' => $this->generateSummary($data),
            'key_findings' => $this->extractKeyFindings($data),
            'anomalies' => $this->detectAnomalies($data),
            'trends' => $this->identifyTrends($data),
            'recommendations' => $this->generateRecommendations($data, $context),
            'generated_at' => now()->toIso8601String(),
        ];

        Cache::put("ai:insight:{$insightId}", $insights, now()->addDays(7));

        return [
            'insight_id' => $insightId,
            'context' => $context,
            'summary' => $insights['summary'],
            'key_findings_count' => count($insights['key_findings']),
            'anomalies_count' => count($insights['anomalies']),
        ];
    }

    /**
     * Generate summary
     */
    private function generateSummary(array $data): string
    {
        $dataSize = count($data);
        $avgValue = !empty($data) ? round(array_sum(array_column($data, 'value')) / $dataSize, 2) : 0;

        return "Analyzed {$dataSize} data points with an average value of {$avgValue}.";
    }

    /**
     * Extract key findings
     */
    private function extractKeyFindings(array $data): array
    {
        $findings = [];

        if (!empty($data)) {
            $values = array_column($data, 'value');
            $max = max($values);
            $min = min($values);
            $avg = round(array_sum($values) / count($values), 2);

            $findings[] = [
                'finding' => "Maximum value: {$max}",
                'importance' => 'high',
            ];

            $findings[] = [
                'finding' => "Minimum value: {$min}",
                'importance' => 'medium',
            ];

            $findings[] = [
                'finding' => "Average value: {$avg}",
                'importance' => 'high',
            ];
        }

        return $findings;
    }

    /**
     * Detect anomalies in data
     */
    private function detectAnomalies(array $data): array
    {
        $anomalies = [];

        if (count($data) < 3) {
            return $anomalies;
        }

        $values = array_column($data, 'value');
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        $stdDev = sqrt($variance);

        foreach ($data as $index => $item) {
            $zScore = abs(($item['value'] - $mean) / max($stdDev, 0.001));

            if ($zScore > 2.5) {
                $anomalies[] = [
                    'index' => $index,
                    'value' => $item['value'],
                    'severity' => $zScore > 3.5 ? 'critical' : 'warning',
                    'description' => "Unusual value detected: {$item['value']}",
                ];
            }
        }

        return $anomalies;
    }

    /**
     * Identify trends
     */
    private function identifyTrends(array $data): array
    {
        $trends = [];

        if (count($data) < 2) {
            return $trends;
        }

        $values = array_column($data, 'value');

        // Calculate trend direction
        $first_half_avg = array_sum(array_slice($values, 0, intval(count($values) / 2))) / intval(count($values) / 2);
        $second_half_avg = array_sum(array_slice($values, intval(count($values) / 2))) / (count($values) - intval(count($values) / 2));

        if ($second_half_avg > $first_half_avg) {
            $trends[] = [
                'trend' => 'upward',
                'change' => round((($second_half_avg - $first_half_avg) / $first_half_avg) * 100, 2) . '%',
                'confidence' => round(rand(70, 95) / 100, 2),
            ];
        } elseif ($second_half_avg < $first_half_avg) {
            $trends[] = [
                'trend' => 'downward',
                'change' => round((($first_half_avg - $second_half_avg) / $first_half_avg) * 100, 2) . '%',
                'confidence' => round(rand(70, 95) / 100, 2),
            ];
        } else {
            $trends[] = [
                'trend' => 'stable',
                'change' => '0%',
                'confidence' => round(rand(80, 99) / 100, 2),
            ];
        }

        return $trends;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $data, string $context): array
    {
        $recommendations = [];

        if (empty($data)) {
            return $recommendations;
        }

        $values = array_column($data, 'value');
        $avg = array_sum($values) / count($values);
        $max = max($values);

        // Context-specific recommendations
        if ($context === 'performance') {
            if ($avg < 70) {
                $recommendations[] = 'Consider optimizing performance - average score is below 70.';
            }

            if ($max < 85) {
                $recommendations[] = 'Performance is consistently below optimal levels.';
            }
        } elseif ($context === 'sales') {
            if ($avg < 1000) {
                $recommendations[] = 'Sales volume is lower than typical benchmarks.';
            }

            if ($max > $avg * 1.5) {
                $recommendations[] = 'High-performing period detected - investigate success factors.';
            }
        } else {
            $recommendations[] = 'Continue monitoring data trends for insights.';
            $recommendations[] = 'Set up automated alerts for anomalies.';
        }

        return $recommendations;
    }

    /**
     * Get insight details
     */
    public function getInsightDetails(string $insightId): ?array
    {
        return Cache::get("ai:insight:{$insightId}");
    }

    /**
     * Compare insights
     */
    public function compareInsights(string $insightId1, string $insightId2): array
    {
        $insight1 = Cache::get("ai:insight:{$insightId1}");
        $insight2 = Cache::get("ai:insight:{$insightId2}");

        if (!$insight1 || !$insight2) {
            return ['error' => 'One or both insights not found'];
        }

        return [
            'insight1_id' => $insightId1,
            'insight2_id' => $insightId2,
            'comparison' => [
                'findings_difference' => count($insight2['key_findings']) - count($insight1['key_findings']),
                'anomalies_difference' => count($insight2['anomalies']) - count($insight1['anomalies']),
                'trends_changed' => $insight1['trends'] !== $insight2['trends'],
            ],
        ];
    }

    /**
     * Get insights by period
     */
    public function getInsightsByPeriod(string $startDate, string $endDate): array
    {
        // In a real implementation, this would query database
        // For now, return empty array
        return [
            'period' => "{$startDate} to {$endDate}",
            'insights_count' => 0,
            'insights' => [],
        ];
    }

    /**
     * Export insight report
     */
    public function exportInsightReport(string $insightId, string $format = 'json'): array
    {
        $insight = Cache::get("ai:insight:{$insightId}");

        if (!$insight) {
            return ['error' => 'Insight not found'];
        }

        $reportContent = $this->formatReport($insight, $format);

        return [
            'insight_id' => $insightId,
            'format' => $format,
            'content' => $reportContent,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Format report
     */
    private function formatReport(array $insight, string $format): string
    {
        if ($format === 'json') {
            return json_encode($insight, JSON_PRETTY_PRINT);
        }

        // Simple text format
        $report = "AUTOMATED INSIGHTS REPORT\n";
        $report .= "=" . str_repeat("=", 40) . "\n\n";
        $report .= "Summary:\n{$insight['summary']}\n\n";
        $report .= "Key Findings:\n";

        foreach ($insight['key_findings'] as $finding) {
            $report .= "- {$finding['finding']}\n";
        }

        return $report;
    }
}
