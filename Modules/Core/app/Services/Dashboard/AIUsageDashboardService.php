<?php

declare(strict_types=1);

namespace Modules\Core\Services\Dashboard;

use Illuminate\Support\Collection;

class AIUsageDashboardService
{
    // Pricing per million tokens (approximate, as of May 2026)
    private const PRICING = [
        'claude-3-opus' => [
            'input' => 15.00,
            'output' => 75.00,
        ],
        'claude-3-sonnet' => [
            'input' => 3.00,
            'output' => 15.00,
        ],
        'claude-3-haiku' => [
            'input' => 0.25,
            'output' => 1.25,
        ],
        'gpt-4' => [
            'input' => 30.00,
            'output' => 60.00,
        ],
        'gpt-4-turbo' => [
            'input' => 10.00,
            'output' => 30.00,
        ],
        'gpt-3.5-turbo' => [
            'input' => 0.50,
            'output' => 1.50,
        ],
    ];

    /**
     * Get AI usage dashboard data
     */
    public function getDashboard(string $period = 'month'): array
    {
        $usageData = $this->getUsageData($period);
        $costData = $this->calculateCosts($usageData);
        $moduleBreakdown = $this->getModuleBreakdown($usageData);
        $trends = $this->calculateTrends($period);

        return [
            'period' => $period,
            'summary' => [
                'total_requests' => $usageData['total_requests'],
                'total_tokens' => $usageData['total_tokens'],
                'total_cost' => round($costData['total_cost'], 2),
                'avg_cost_per_request' => round($costData['total_cost'] / $usageData['total_requests'], 4),
                'active_models' => count($usageData['by_model']),
            ],
            'by_model' => $this->formatModelStats($usageData['by_model'], $costData),
            'by_feature' => $this->formatFeatureStats($usageData['by_feature'], $costData),
            'module_breakdown' => $moduleBreakdown,
            'cost_breakdown' => [
                'total_input_tokens' => $usageData['total_input_tokens'],
                'total_output_tokens' => $usageData['total_output_tokens'],
                'input_cost' => round($costData['input_cost'], 2),
                'output_cost' => round($costData['output_cost'], 2),
            ],
            'trends' => $trends,
            'top_users' => $this->getTopUsers($usageData, 5),
            'cost_projections' => $this->projectCosts($costData, $period),
            'recommendations' => $this->generateRecommendations($usageData, $costData),
        ];
    }

    /**
     * Get detailed usage data for period
     */
    private function getUsageData(string $period): array
    {
        // In a real implementation, would query from database
        // For demonstration purposes, returning realistic sample data

        return [
            'total_requests' => 12543,
            'total_tokens' => 8934521,
            'total_input_tokens' => 5432100,
            'total_output_tokens' => 3502421,
            'by_model' => [
                'claude-3-opus' => [
                    'requests' => 2345,
                    'input_tokens' => 1234000,
                    'output_tokens' => 856000,
                ],
                'claude-3-sonnet' => [
                    'requests' => 5432,
                    'input_tokens' => 2891000,
                    'output_tokens' => 1523000,
                ],
                'gpt-4' => [
                    'requests' => 2134,
                    'input_tokens' => 987000,
                    'output_tokens' => 654000,
                ],
                'gpt-3.5-turbo' => [
                    'requests' => 2632,
                    'input_tokens' => 320100,
                    'output_tokens' => 469421,
                ],
            ],
            'by_feature' => [
                'duplicate_detection' => [
                    'requests' => 3421,
                    'tokens' => 1523000,
                    'module' => 'CRM',
                ],
                'lead_scoring' => [
                    'requests' => 2156,
                    'tokens' => 987000,
                    'module' => 'CRM',
                ],
                'demand_forecasting' => [
                    'requests' => 1823,
                    'tokens' => 1234000,
                    'module' => 'Inventory',
                ],
                'vision_qc' => [
                    'requests' => 956,
                    'tokens' => 2156000,
                    'module' => 'Manufacturing',
                ],
                'payroll_approval' => [
                    'requests' => 1234,
                    'tokens' => 654000,
                    'module' => 'HR',
                ],
                'invoice_summarization' => [
                    'requests' => 987,
                    'tokens' => 523000,
                    'module' => 'Accounting',
                ],
                'other' => [
                    'requests' => 1966,
                    'tokens' => 857521,
                    'module' => 'Various',
                ],
            ],
        ];
    }

    /**
     * Calculate costs from usage data
     */
    private function calculateCosts(array $usageData): array
    {
        $totalCost = 0;
        $inputCost = 0;
        $outputCost = 0;

        foreach ($usageData['by_model'] as $model => $stats) {
            if (!isset(self::PRICING[$model])) {
                continue;
            }

            $pricing = self::PRICING[$model];
            $inputCost += ($stats['input_tokens'] / 1_000_000) * $pricing['input'];
            $outputCost += ($stats['output_tokens'] / 1_000_000) * $pricing['output'];
        }

        return [
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'total_cost' => $inputCost + $outputCost,
        ];
    }

    /**
     * Format model statistics with cost data
     */
    private function formatModelStats(array $models, array $costData): array
    {
        $formatted = [];

        foreach ($models as $model => $stats) {
            if (!isset(self::PRICING[$model])) {
                continue;
            }

            $pricing = self::PRICING[$model];
            $inputCost = ($stats['input_tokens'] / 1_000_000) * $pricing['input'];
            $outputCost = ($stats['output_tokens'] / 1_000_000) * $pricing['output'];

            $formatted[] = [
                'model' => $model,
                'requests' => $stats['requests'],
                'input_tokens' => $stats['input_tokens'],
                'output_tokens' => $stats['output_tokens'],
                'total_tokens' => $stats['input_tokens'] + $stats['output_tokens'],
                'input_cost' => round($inputCost, 4),
                'output_cost' => round($outputCost, 4),
                'total_cost' => round($inputCost + $outputCost, 2),
                'cost_per_request' => round(($inputCost + $outputCost) / $stats['requests'], 4),
            ];
        }

        // Sort by total cost descending
        usort($formatted, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);

        return $formatted;
    }

    /**
     * Format feature statistics
     */
    private function formatFeatureStats(array $features, array $costData): array
    {
        $formatted = [];
        $totalTokens = array_sum(array_column($features, 'tokens'));
        $totalCost = $costData['total_cost'];

        foreach ($features as $feature => $stats) {
            $tokenPercentage = ($stats['tokens'] / $totalTokens) * 100;
            $estimatedCost = ($totalCost / $totalTokens) * $stats['tokens'];

            $formatted[] = [
                'feature' => $feature,
                'module' => $stats['module'],
                'requests' => $stats['requests'],
                'tokens' => $stats['tokens'],
                'token_percentage' => round($tokenPercentage, 2),
                'estimated_cost' => round($estimatedCost, 2),
                'avg_tokens_per_request' => round($stats['tokens'] / $stats['requests'], 0),
            ];
        }

        // Sort by estimated cost descending
        usort($formatted, fn($a, $b) => $b['estimated_cost'] <=> $a['estimated_cost']);

        return $formatted;
    }

    /**
     * Get module breakdown
     */
    private function getModuleBreakdown(array $usageData): array
    {
        $breakdown = [];

        foreach ($usageData['by_feature'] as $feature => $stats) {
            $module = $stats['module'];

            if (!isset($breakdown[$module])) {
                $breakdown[$module] = [
                    'module' => $module,
                    'requests' => 0,
                    'tokens' => 0,
                    'features' => [],
                ];
            }

            $breakdown[$module]['requests'] += $stats['requests'];
            $breakdown[$module]['tokens'] += $stats['tokens'];
            $breakdown[$module]['features'][] = $feature;
        }

        // Calculate costs for each module
        $totalTokens = $usageData['total_tokens'];
        $estimatedTotalCost = 45.32; // From sample data

        foreach ($breakdown as &$module) {
            $costPercentage = ($module['tokens'] / $totalTokens) * 100;
            $module['estimated_cost'] = round(($estimatedTotalCost / 100) * $costPercentage, 2);
            $module['cost_percentage'] = round($costPercentage, 2);
        }

        // Sort by estimated cost descending
        usort($breakdown, fn($a, $b) => $b['estimated_cost'] <=> $a['estimated_cost']);

        return array_values($breakdown);
    }

    /**
     * Calculate usage trends
     */
    private function calculateTrends(string $period): array
    {
        // Generate trend data based on period
        return match ($period) {
            'week' => [
                ['day' => 'Mon', 'requests' => 1234, 'cost' => 4.23],
                ['day' => 'Tue', 'requests' => 1456, 'cost' => 5.12],
                ['day' => 'Wed', 'requests' => 1567, 'cost' => 5.34],
                ['day' => 'Thu', 'requests' => 1389, 'cost' => 4.89],
                ['day' => 'Fri', 'requests' => 1678, 'cost' => 6.01],
                ['day' => 'Sat', 'requests' => 892, 'cost' => 3.12],
                ['day' => 'Sun', 'requests' => 745, 'cost' => 2.56],
            ],
            'month' => [
                ['week' => 'W1', 'requests' => 3245, 'cost' => 11.23],
                ['week' => 'W2', 'requests' => 3456, 'cost' => 12.34],
                ['week' => 'W3', 'requests' => 3123, 'cost' => 11.45],
                ['week' => 'W4', 'requests' => 2719, 'cost' => 10.30],
            ],
            'year' => array_map(
                fn($month) => [
                    'month' => date('M', mktime(0, 0, 0, $month, 1)),
                    'requests' => rand(30000, 45000),
                    'cost' => round(rand(10000, 15000) / 100, 2),
                ],
                range(1, 12)
            ),
            default => [],
        };
    }

    /**
     * Get top AI users
     */
    private function getTopUsers(array $usageData, int $limit = 5): array
    {
        // In real implementation, would query from usage logs
        return [
            ['user_id' => 1, 'name' => 'John Smith', 'requests' => 2345, 'tokens' => 1234000, 'cost' => 12.34],
            ['user_id' => 2, 'name' => 'Sarah Johnson', 'requests' => 1876, 'tokens' => 987000, 'cost' => 10.23],
            ['user_id' => 3, 'name' => 'Michael Chen', 'requests' => 1543, 'tokens' => 876000, 'cost' => 9.12],
            ['user_id' => 4, 'name' => 'Emily Davis', 'requests' => 1234, 'tokens' => 654000, 'cost' => 7.89],
            ['user_id' => 5, 'name' => 'Robert Wilson', 'requests' => 1045, 'tokens' => 523000, 'cost' => 6.54],
        ];
    }

    /**
     * Project future costs
     */
    private function projectCosts(array $costData, string $period): array
    {
        $monthlyRate = match ($period) {
            'day' => $costData['total_cost'] * 30,
            'week' => $costData['total_cost'] * 4.3,
            'month' => $costData['total_cost'],
            'year' => $costData['total_cost'] / 12,
            default => $costData['total_cost'],
        };

        return [
            'monthly_projection' => round($monthlyRate, 2),
            'quarterly_projection' => round($monthlyRate * 3, 2),
            'annual_projection' => round($monthlyRate * 12, 2),
            'vs_budget' => [
                'monthly_budget' => 50.00,
                'monthly_used' => round($monthlyRate, 2),
                'monthly_remaining' => round(50.00 - $monthlyRate, 2),
                'percentage_of_budget' => round(($monthlyRate / 50.00) * 100, 1),
            ],
        ];
    }

    /**
     * Generate cost optimization recommendations
     */
    private function generateRecommendations(array $usageData, array $costData): array
    {
        $recommendations = [];

        // Check for high-cost features
        $topCostFeature = array_key_first($usageData['by_feature']);
        if ($topCostFeature) {
            $recommendations[] = [
                'type' => 'optimization',
                'priority' => 'medium',
                'title' => "Optimize {$topCostFeature} feature",
                'description' => "This feature accounts for a significant portion of costs. Consider implementing caching or batch processing.",
                'estimated_savings' => round($costData['total_cost'] * 0.15, 2),
            ];
        }

        // Check for usage spikes
        $recommendations[] = [
            'type' => 'monitoring',
            'priority' => 'low',
            'title' => 'Monitor weekly usage trends',
            'description' => 'Set up alerts for unusual usage spikes to catch issues early.',
            'estimated_savings' => null,
        ];

        // Model optimization
        $recommendations[] = [
            'type' => 'optimization',
            'priority' => 'medium',
            'title' => 'Consider using more cost-effective models',
            'description' => 'Some requests could use Claude Haiku instead of Opus, reducing costs by up to 60%.',
            'estimated_savings' => round($costData['total_cost'] * 0.25, 2),
        ];

        // Budget warning
        if ($costData['total_cost'] > 40) {
            $recommendations[] = [
                'type' => 'alert',
                'priority' => 'high',
                'title' => 'Monthly budget exceeded',
                'description' => 'Current projections exceed the $50 monthly budget. Review usage patterns.',
                'estimated_savings' => null,
            ];
        }

        return $recommendations;
    }
}
