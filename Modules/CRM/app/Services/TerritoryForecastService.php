<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Territory;

class TerritoryForecastService
{
    /**
     * Einstein-style pipeline forecast by territory.
     *
     * Groups opportunities by territory → by stage → computes weighted revenue forecast.
     */
    public function territoryForecast(): array
    {
        $territories = Territory::query()
            ->where('is_active', true)
            ->with(['opportunities.score', 'assignedTo'])
            ->get();

        $territoryData = [];
        $totalForecast = 0.0;
        $totalTarget = 0.0;

        foreach ($territories as $territory) {
            $ytdRev = $territory->ytdRevenue();
            $forecastRev = $territory->forecastedRevenue();
            $quotaForecast = $territory->quotaForecast();

            $totalForecast += $forecastRev;
            $totalTarget += (float) $territory->sales_target;

            $pipelineByStage = $this->getPipelineByStage($territory);

            $territoryData[$territory->id] = [
                'id' => $territory->id,
                'territory_name' => $territory->name,
                'assigned_to_name' => $territory->assignedTo->name ?? 'Unassigned',
                'sales_target' => (float) $territory->sales_target,
                'currency' => $territory->currency,
                'ytd_revenue' => $ytdRev,
                'quota_attainment' => $territory->quotaAttainment(),
                'forecast_revenue' => $forecastRev,
                'quota_forecast' => $quotaForecast,
                'pipeline_by_stage' => $pipelineByStage,
            ];
        }

        $aggregateQuotaForecast = $totalTarget > 0 ? ($totalForecast / $totalTarget) * 100 : 0.0;

        return [
            'territories' => $territoryData,
            'summary' => [
                'total_forecast' => $totalForecast,
                'total_target' => $totalTarget,
                'aggregate_quota_forecast' => $aggregateQuotaForecast,
            ],
        ];
    }

    /**
     * Get detailed forecast for a single territory.
     */
    public function territoryDetail(Territory $territory): array
    {
        $pipelineByStage = $this->getPipelineByStage($territory);
        $timeline = $this->getTimelineData($territory);
        $atRisk = $this->atRiskOpportunities($territory)->count();

        return [
            'territory' => [
                'id' => $territory->id,
                'name' => $territory->name,
                'code' => $territory->code,
                'assigned_to_name' => $territory->assignedTo->name ?? 'Unassigned',
                'region' => $territory->region,
                'sales_target' => (float) $territory->sales_target,
                'currency' => $territory->currency,
                'ytd_revenue' => $territory->ytdRevenue(),
                'quota_attainment' => $territory->quotaAttainment(),
                'forecast_revenue' => $territory->forecastedRevenue(),
                'quota_forecast' => $territory->quotaForecast(),
            ],
            'stages' => $pipelineByStage,
            'timeline' => $timeline,
            'at_risk_count' => $atRisk,
        ];
    }

    /**
     * Get opportunities "at risk" (score < 50 or high aging) for a territory.
     */
    public function atRiskOpportunities(Territory $territory): Collection
    {
        $ninetyDaysAgo = now()->subDays(90);

        $opportunities = $territory->opportunities()
            ->where('status', '!=', 'closed_lost')
            ->where('status', '!=', 'closed_won')
            ->with('score')
            ->get();

        /** @phpstan-ignore-next-line */
        return $opportunities->filter(function (Opportunity $opportunity, int $key) use ($ninetyDaysAgo): bool {
            $totalScore = $opportunity->score?->total_score ?? 50;
            $isAging = $opportunity->updated_at < $ninetyDaysAgo;

            return $totalScore < 50 || $isAging;
        });
    }

    /**
     * Assign an opportunity to a territory.
     */
    public function assignOpportunity(Opportunity $opportunity, Territory $territory): Opportunity
    {
        $opportunity->update(['territory_id' => $territory->id]);

        return $opportunity->refresh();
    }

    /**
     * Get forecast comparison: territory forecast vs. target vs. previous period.
     */
    public function forecastVsTarget(Territory $territory): array
    {
        $currentForecast = $territory->forecastedRevenue();
        $currentTarget = (float) $territory->sales_target;
        $ytd = $territory->ytdRevenue();

        $variance = $currentForecast - $currentTarget;
        $variancePercent = $currentTarget > 0 ? ($variance / $currentTarget) * 100 : 0.0;

        return [
            'territory_id' => $territory->id,
            'territory_name' => $territory->name,
            'ytd_revenue' => $ytd,
            'sales_target' => $currentTarget,
            'forecast_revenue' => $currentForecast,
            'variance' => $variance,
            'variance_percent' => $variancePercent,
            'quota_attainment' => $territory->quotaAttainment(),
            'quota_forecast' => $territory->quotaForecast(),
            'status' => $this->getStatus($currentForecast, $currentTarget),
        ];
    }

    /**
     * Get pipeline breakdown by stage for a territory.
     */
    private function getPipelineByStage(Territory $territory): array
    {
        $opportunities = $territory->opportunities()
            ->where('status', '!=', 'closed_lost')
            ->with('score')
            ->get();

        $stages = ['prospecting', 'qualification', 'proposal', 'negotiation'];
        $pipelineData = [];

        foreach ($stages as $stage) {
            $stageOpps = $opportunities->filter(fn ($opp) => $opp->stage === $stage);

            $totalAmount = $stageOpps->sum('amount');
            $weightedForecast = $stageOpps->reduce(function ($carry, $opp) {
                $winProb = $opp->score?->win_probability ?? ($opp->probability / 100);

                return $carry + ((float) $opp->amount * (float) $winProb);
            }, 0.0);

            $pipelineData[$stage] = [
                'count' => $stageOpps->count(),
                'total_amount' => (float) $totalAmount,
                'weighted_forecast' => $weightedForecast,
            ];
        }

        // Add closed_won separately
        $closedWon = $territory->opportunities()
            ->where('status', 'closed_won')
            ->get();

        $pipelineData['closed_won'] = [
            'count' => $closedWon->count(),
            'total_amount' => (float) $closedWon->sum('amount'),
            'weighted_forecast' => (float) $closedWon->sum('amount'),
        ];

        return $pipelineData;
    }

    /**
     * Get timeline data for a territory (expected closes by month).
     */
    private function getTimelineData(Territory $territory): array
    {
        $opportunities = $territory->opportunities()
            ->where('status', '!=', 'closed_lost')
            ->whereNotNull('expected_close_date')
            ->with('score')
            ->get();

        $timeline = [];

        foreach ($opportunities as $opp) {
            $month = $opp->expected_close_date->format('Y-m');

            if (! isset($timeline[$month])) {
                $timeline[$month] = [
                    'month' => $month,
                    'count' => 0,
                    'total_amount' => 0.0,
                    'weighted_forecast' => 0.0,
                ];
            }

            $winProb = $opp->score?->win_probability ?? ($opp->probability / 100);

            $timeline[$month]['count'] += 1;
            $timeline[$month]['total_amount'] += (float) $opp->amount;
            $timeline[$month]['weighted_forecast'] += (float) $opp->amount * (float) $winProb;
        }

        return array_values($timeline);
    }

    /**
     * Determine status based on forecast vs target.
     */
    private function getStatus(float $forecast, float $target): string
    {
        if ($target == 0) {
            return 'no_target';
        }

        $percent = ($forecast / $target) * 100;

        return match (true) {
            $percent >= 100 => 'on_track',
            $percent >= 75 => 'at_risk',
            default => 'at_serious_risk',
        };
    }
}
