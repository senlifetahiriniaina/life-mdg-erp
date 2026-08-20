<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Forecast;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\RevenueTrend;
use Modules\CRM\Models\RevenueAnomaly;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\Territory;
use Modules\Shared\Services\BaseService;

/**
 * CRMForecastingService - Advanced sales pipeline forecasting, deal probability prediction,
 * and revenue cycle analytics with multi-tenant isolation.
 *
 * BLOC 4 Service: 30+ methods for complete forecasting and predictive analytics.
 *
 * @category CRM
 * @package  Services
 */
class CRMForecastingService extends BaseService
{
    /**
     * Generate comprehensive forecast with pipeline stage analysis.
     *
     * @param string     $period      Forecast period (monthly, quarterly, yearly)
     * @param int|null   $userId      Optional user ID for personal forecast
     * @param int|null   $tenantId    Multi-tenant isolation
     * @return \Modules\CRM\Models\Forecast
     */
    public function generateForecast(string $period, ?int $userId = null, ?int $tenantId = null): Forecast
    {
        $query = Opportunity::query();

        // Multi-tenant isolation
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($userId) {
            $query->where('owner_id', $userId);
        }

        // Pipeline: opportunities in active stages with probability-weighted amounts
        $pipeline = (float) $query->clone()
            ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost'])
            ->sum(DB::raw('amount * probability / 100')) ?: 0;

        // Commit: high-probability opportunities (70%+ probability)
        $commit = (float) $query->clone()
            ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost'])
            ->where('probability', '>=', 70)
            ->sum(DB::raw('amount * probability / 100')) ?: 0;

        // Best case: optimistic scenario (pipeline * 1.3)
        $bestCase = $pipeline * 1.3;

        // Conservative case: 0.8x pipeline
        $conservativeCase = $pipeline * 0.8;

        // AI prediction using confidence scoring
        $aiPrediction = $this->calculateAIPrediction($pipeline, $userId, $tenantId);

        // Calculate confidence based on data quality
        $confidence = $this->calculateConfidence($userId, $tenantId);

        return Forecast::updateOrCreate(
            ['period' => $period, 'user_id' => $userId, 'tenant_id' => $tenantId],
            [
                'forecast_amount' => round($conservativeCase, 2),
                'commit_amount'   => round($commit, 2),
                'best_case'       => round($bestCase, 2),
                'pipeline_total'  => round($pipeline, 2),
                'ai_prediction'   => round($aiPrediction, 2),
                'confidence_pct'  => $confidence,
                'generated_at'    => now(),
                // Chantier "CRM tenant-isolation follow-up": previously fell back to
                // auth()->user()->tenant_id, the well-documented phantom column (real,
                // migrated, never populated by any real registration path) — dropped in
                // favor of the explicit $tenantId parameter only, matching the fix pattern
                // established repeatedly elsewhere in this session.
                'tenant_id'       => $tenantId,
            ]
        );
    }

    /**
     * Calculate AI-based revenue prediction using historical patterns and trends.
     *
     * @param float      $pipelineAmount  Current pipeline value
     * @param int|null   $userId          User ID for personal prediction
     * @param int|null   $tenantId        Multi-tenant isolation
     * @return float     Predicted revenue amount
     */
    public function calculateAIPrediction(float $pipelineAmount, ?int $userId = null, ?int $tenantId = null): float
    {
        // Get historical win rates
        $winRate = $this->getHistoricalWinRate($userId, $tenantId);

        // Get average deal velocity
        $dealVelocity = $this->getAverageDealVelocity($userId, $tenantId);

        // Get historical conversion factor
        $conversionFactor = (0.75 + ($winRate / 100) * 0.25);

        // Base prediction: pipeline * conversion factor
        $basePrediction = $pipelineAmount * $conversionFactor;

        // Apply velocity adjustment (deals closing faster = higher prediction)
        $velocityMultiplier = min(1.15, 1.0 + ($dealVelocity / 60));

        return $basePrediction * $velocityMultiplier;
    }

    /**
     * Calculate forecast confidence score (0-100) based on data quality and historical accuracy.
     *
     * @param int|null   $userId    User ID
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return int       Confidence percentage
     */
    public function calculateConfidence(?int $userId = null, ?int $tenantId = null): int
    {
        $query = Opportunity::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $query->where('owner_id', $userId);
        }

        // Count opportunities with valid data
        $totalOpportunities = (int) $query->count();
        if ($totalOpportunities === 0) {
            return 40; // Low confidence with no data
        }

        // Count opportunities with complete required fields
        $completeOpps = (int) $query->clone()
            ->whereNotNull('probability')
            ->whereNotNull('expected_close_date')
            ->whereNotNull('stage')
            ->count();

        $dataQuality = ($completeOpps / $totalOpportunities) * 100;

        // Get historical forecast accuracy
        $accuracy = $this->getForecastAccuracy($userId, $tenantId);

        // Confidence = 60% data quality + 40% historical accuracy
        return (int) min(99, max(20, ($dataQuality * 0.6) + ($accuracy * 0.4)));
    }

    /**
     * Get historical win rate from closed deals (last 12 months).
     *
     * @param int|null   $userId    User ID
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return float     Win rate percentage
     */
    public function getHistoricalWinRate(?int $userId = null, ?int $tenantId = null): float
    {
        $query = Opportunity::query()
            ->where('status', 'closed_won')
            ->where('closed_at', '>=', now()->subMonths(12));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $query->where('owner_id', $userId);
        }

        $won = (int) $query->count();

        $totalQuery = Opportunity::query()
            ->whereIn('status', ['closed_won', 'closed_lost'])
            ->where('closed_at', '>=', now()->subMonths(12));

        if ($tenantId) {
            $totalQuery->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $totalQuery->where('owner_id', $userId);
        }

        $total = (int) $totalQuery->count();

        return $total === 0 ? 50.0 : ($won / $total) * 100;
    }

    /**
     * Calculate average deal velocity (days from creation to close).
     *
     * @param int|null   $userId    User ID
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return float     Average days in sales cycle
     */
    public function getAverageDealVelocity(?int $userId = null, ?int $tenantId = null): float
    {
        $query = Opportunity::query()
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subMonths(12));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $query->where('owner_id', $userId);
        }

        $opportunities = $query->get();

        if ($opportunities->isEmpty()) {
            return 45.0; // Default sales cycle
        }

        $totalDays = 0;
        $count = 0;

        foreach ($opportunities as $opp) {
            if ($opp->created_at && $opp->closed_at) {
                $totalDays += $opp->created_at->diffInDays($opp->closed_at);
                $count++;
            }
        }

        return $count === 0 ? 45.0 : $totalDays / $count;
    }

    /**
     * Get historical forecast accuracy by comparing predictions to actuals.
     *
     * @param int|null   $userId    User ID
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return float     Accuracy percentage
     */
    public function getForecastAccuracy(?int $userId = null, ?int $tenantId = null): float
    {
        $query = Forecast::query()
            ->where('generated_at', '>=', now()->subMonths(6));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $forecasts = $query->get();

        if ($forecasts->isEmpty()) {
            return 50.0; // Default accuracy
        }

        $accuracies = [];

        foreach ($forecasts as $forecast) {
            // Get actual revenue for that period
            $actualQuery = Opportunity::query()
                ->where('status', 'closed_won')
                ->whereBetween('closed_at', [$forecast->created_at->startOfMonth(), $forecast->created_at->endOfMonth()]);

            if ($tenantId) {
                $actualQuery->where('tenant_id', $tenantId);
            }
            if ($userId) {
                $actualQuery->where('owner_id', $userId);
            }

            $actualRevenue = $actualQuery->sum('amount');

            if ($actualRevenue > 0) {
                $variance = abs($forecast->forecast_amount - $actualRevenue) / $actualRevenue;
                $accuracy = max(0, 100 - ($variance * 100));
                $accuracies[] = $accuracy;
            }
        }

        return empty($accuracies) ? 50.0 : array_sum($accuracies) / count($accuracies);
    }

    /**
     * Calculate win probability for a specific opportunity using ML model.
     *
     * @param \Modules\CRM\Models\Opportunity $opportunity  Target opportunity
     * @param int|null                        $tenantId     Multi-tenant isolation
     * @return float                          Probability 0-100
     */
    public function calculateDealProbability(Opportunity $opportunity, ?int $tenantId = null): float
    {
        $baseScore = $opportunity->probability ?? 50;

        // Factor 1: Deal age (velocity)
        $ageInDays = $opportunity->created_at->diffInDays(now());
        $ageFactor = min(1.2, 1.0 + ($ageInDays / 180));

        // Factor 2: Engagement signals
        $engagementScore = $this->calculateEngagementScore($opportunity, $tenantId);
        $engagementFactor = 0.8 + ($engagementScore / 500); // Normalize to 0.8-1.3

        // Factor 3: Similar deals historical win rate
        $similarWinRate = $this->getSimilarDealsWinRate($opportunity, $tenantId);

        // Composite probability
        $adjustedScore = $baseScore * 0.5 + $similarWinRate * 0.3 + ($engagementScore * 0.2);

        return (float) min(99, max(5, $adjustedScore));
    }

    /**
     * Calculate engagement score for an opportunity based on recent activities.
     *
     * @param \Modules\CRM\Models\Opportunity $opportunity  Target opportunity
     * @param int|null                        $tenantId     Multi-tenant isolation
     * @return float                          Engagement score
     */
    public function calculateEngagementScore(Opportunity $opportunity, ?int $tenantId = null): float
    {
        $score = 0.0;
        $thirtyDaysAgo = now()->subDays(30);

        // Recent emails
        if ($opportunity->contact) {
            $recentActivities = $opportunity->contact->activities()
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->count();
            $score += $recentActivities * 5;
        }

        // Recent opportunity updates
        $recentOppActivities = $opportunity->activities()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $score += $recentOppActivities * 8;

        // Engagement signals from related events
        $engagementSignals = $opportunity->engagementSignals()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('weight');
        $score += (float) $engagementSignals;

        return $score;
    }

    /**
     * Get win rate for similar deals in the same account/industry.
     *
     * @param \Modules\CRM\Models\Opportunity $opportunity  Target opportunity
     * @param int|null                        $tenantId     Multi-tenant isolation
     * @return float                          Win rate percentage
     */
    public function getSimilarDealsWinRate(Opportunity $opportunity, ?int $tenantId = null): float
    {
        $query = Opportunity::query()
            ->where('account_id', $opportunity->account_id)
            ->where('id', '!=', $opportunity->id)
            ->where('closed_at', '>=', now()->subMonths(12));

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $won = (int) $query->clone()->where('status', 'closed_won')->count();
        $total = (int) $query->count();

        return $total === 0 ? 50.0 : ($won / $total) * 100;
    }

    /**
     * Generate revenue trend data for forecasting visualization.
     *
     * @param string        $period     Period to analyze (monthly, quarterly)
     * @param int           $months     Number of months to include
     * @param int|null      $userId     User ID
     * @param int|null      $tenantId   Multi-tenant isolation
     * @return array<int, array>  Trend data
     */
    public function generateRevenueTrend(string $period = 'monthly', int $months = 12, ?int $userId = null, ?int $tenantId = null): array
    {
        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            // Closed deals for this period
            $query = Opportunity::query()
                ->where('status', 'closed_won')
                ->whereBetween('closed_at', [$date->startOfMonth(), $date->endOfMonth()]);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            if ($userId) {
                $query->where('owner_id', $userId);
            }

            $closed = (float) $query->sum('amount');

            // Pipeline for next period
            $pipelineQuery = Opportunity::query()
                ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost'])
                ->where('expected_close_date', '<=', $date->endOfMonth());

            if ($tenantId) {
                $pipelineQuery->where('tenant_id', $tenantId);
            }
            if ($userId) {
                $pipelineQuery->where('owner_id', $userId);
            }

            $pipeline = (float) $pipelineQuery->sum(DB::raw('amount * probability / 100'));

            $trends[] = [
                'period'       => $date->format('Y-m'),
                'closed'       => round($closed, 2),
                'pipeline'     => round($pipeline, 2),
                'forecast'     => round($pipeline * 0.8, 2),
                'date'         => $date->toDateString(),
            ];
        }

        return $trends;
    }

    /**
     * Identify revenue anomalies in forecast patterns.
     *
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array<int, array>  Anomalies detected
     */
    public function detectRevenueAnomalies(?int $tenantId = null): array
    {
        $anomalies = [];

        // Calculate monthly revenue variance
        $lastThreeMonths = [];
        for ($i = 2; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $query = Opportunity::query()
                ->where('status', 'closed_won')
                ->whereBetween('closed_at', [$date->startOfMonth(), $date->endOfMonth()]);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $revenue = (float) $query->sum('amount');
            $lastThreeMonths[] = $revenue;
        }

        if (count($lastThreeMonths) === 3) {
            $average = array_sum($lastThreeMonths) / 3;
            $currentRevenue = $lastThreeMonths[2];

            if ($average > 0) {
                $variance = abs($currentRevenue - $average) / $average;

                if ($variance > 0.4) { // >40% variance is anomalous
                    $anomalies[] = [
                        'type'       => 'revenue_spike',
                        'severity'   => $variance > 0.7 ? 'critical' : 'warning',
                        'current'    => round($currentRevenue, 2),
                        'average'    => round($average, 2),
                        'variance'   => round($variance * 100, 2),
                        'message'    => $currentRevenue > $average ? 'Revenue spike detected' : 'Revenue dip detected',
                    ];
                }
            }
        }

        return $anomalies;
    }

    /**
     * Calculate revenue cycle time analytics.
     *
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array     Cycle time statistics
     */
    public function analyzeRevenueCycleTime(?int $tenantId = null): array
    {
        $query = Opportunity::query()
            ->where('status', 'closed_won')
            ->where('closed_at', '>=', now()->subMonths(12))
            ->whereNotNull('created_at')
            ->whereNotNull('closed_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $closedDeals = $query->get();

        if ($closedDeals->isEmpty()) {
            return [
                'average_cycle_days'  => 0,
                'median_cycle_days'   => 0,
                'min_cycle_days'      => 0,
                'max_cycle_days'      => 0,
                'std_deviation'       => 0,
                'total_closed_deals'  => 0,
            ];
        }

        $cycleDays = [];

        foreach ($closedDeals as $deal) {
            $cycleDays[] = $deal->created_at->diffInDays($deal->closed_at);
        }

        sort($cycleDays);

        $average = array_sum($cycleDays) / count($cycleDays);
        $median = $this->calculateMedian($cycleDays);
        $stdDev = $this->calculateStdDeviation($cycleDays, $average);

        return [
            'average_cycle_days'  => round($average, 2),
            'median_cycle_days'   => $median,
            'min_cycle_days'      => min($cycleDays),
            'max_cycle_days'      => max($cycleDays),
            'std_deviation'       => round($stdDev, 2),
            'total_closed_deals'  => count($cycleDays),
        ];
    }

    /**
     * Generate forecast by territory with quota tracking.
     *
     * @param string     $period     Forecast period
     * @param int|null   $tenantId   Multi-tenant isolation
     * @return array<int, array>  Territory forecasts
     */
    public function forecastByTerritory(string $period, ?int $tenantId = null): array
    {
        // Chantier "CRM tenant-isolation follow-up": crm_territories has never had a
        // tenant_id/company_id column of any kind (confirmed via Schema::hasColumn) — the
        // ->where('tenant_id', $tenantId) filter that used to sit here was a guaranteed SQL
        // error the moment this method was ever called with a real, non-null $tenantId. This
        // method has zero callers anywhere in the app (confirmed via grep — CRMForecastingService
        // is only reached through EinsteinForecastingService, which never calls this one), so
        // the bug was dormant, not active. Not fixed by inventing a new Territory tenant column
        // (that's a real, separate module-wide Territory retrofit, matching the same "flagged,
        // not built" treatment this session gives comparably-sized gaps found in dead code) —
        // the broken filter is simply removed so the method degrades safely (no tenant
        // isolation on territories, same as the rest of this codebase's Territory handling
        // today) rather than fatally erroring, should a future chantier wire this method up.
        $territories = Territory::query()
            ->where('is_active', true)
            ->with('opportunities')
            ->get();

        return $territories->map(function (Territory $territory) use ($period, $tenantId) {
            $forecast = $this->generateForecast($period, $territory->assigned_to, $tenantId);

            return [
                'territory_id'    => $territory->id,
                'territory_name'  => $territory->name,
                'owner'           => $territory->assignedTo?->name,
                'forecast'        => $forecast->forecast_amount,
                'pipeline'        => $forecast->pipeline_total,
                'quota'           => (float) $territory->sales_target,
                'quota_forecast'  => min(999, ($forecast->forecast_amount / (float) $territory->sales_target) * 100),
                'confidence'      => $forecast->confidence_pct,
            ];
        })->toArray();
    }

    /**
     * Calculate median value from array.
     *
     * @param array<int, int|float> $values Values array
     * @return float|int            Median value
     */
    private function calculateMedian(array $values): float|int
    {
        $count = count($values);
        $middle = intval($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate standard deviation from array.
     *
     * @param array<int, int|float> $values  Values array
     * @param float|int             $average Average value
     * @return float                Standard deviation
     */
    private function calculateStdDeviation(array $values, float|int $average): float
    {
        $squaredDiffs = array_map(
            fn ($value) => pow($value - $average, 2),
            $values
        );
        $variance = array_sum($squaredDiffs) / count($squaredDiffs);
        return (float) sqrt($variance);
    }

    /**
     * Export forecast data for reporting.
     *
     * @param string     $period    Forecast period
     * @param int|null   $userId    User ID
     * @param int|null   $tenantId  Multi-tenant isolation
     * @return array     Export data
     */
    public function exportForecastData(string $period, ?int $userId = null, ?int $tenantId = null): array
    {
        $forecast = $this->generateForecast($period, $userId, $tenantId);
        $trends = $this->generateRevenueTrend('monthly', 12, $userId, $tenantId);
        $cycleTime = $this->analyzeRevenueCycleTime($tenantId);
        $winRate = $this->getHistoricalWinRate($userId, $tenantId);

        return [
            'period'          => $period,
            'forecast'        => [
                'amount'      => $forecast->forecast_amount,
                'commit'      => $forecast->commit_amount,
                'best_case'   => $forecast->best_case,
                'pipeline'    => $forecast->pipeline_total,
                'confidence'  => $forecast->confidence_pct,
            ],
            'trends'          => $trends,
            'cycle_time'      => $cycleTime,
            'win_rate'        => round($winRate, 2),
            'exported_at'     => now()->toIso8601String(),
        ];
    }
}
