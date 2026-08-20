<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\LeadStatusLog;

/**
 * Chantier "CRM tenant-isolation follow-up": every public method here ran a fully
 * tenant-unfiltered Opportunity aggregate (confirmed via read + grep before this fix) — flagged
 * in CLAUDE.md's Chantier 19 Lot 1 entry alongside CRMForecastingService/
 * EinsteinForecastingService/ForecastService/OpportunityScoringService/
 * CampaignOrchestrationService. Unlike those, this class was found to have **zero callers
 * anywhere in the app** (confirmed via a repo-wide grep — no controller, route, job, console
 * command, or test references it; the module's real, live "pipeline" endpoints —
 * PipelineController for stage CRUD, PipelineAnalyticsController for win-rate/conversion/
 * velocity/trend dashboards — are both built on entirely different, real, already-tenant-scoped
 * code paths, not this class). CLAUDE.md's characterization of this service as one that "runs
 * tenant-unfiltered... aggregates" was accurate about the code but implied live exposure that
 * does not exist today. Fixed anyway rather than deleted, on the same "close the landmine
 * before a future chantier wires it up and trips it" precedent already used throughout this
 * session (e.g. HR's ReorderRule, Achats' PoReceiptLine/ReorderRule) — every method now accepts
 * an optional $companyId and filters Opportunity via the real `tenant_id` column
 * (crm_opportunities.tenant_id, added Chantier 10, populated from the acting user's company_id
 * at Opportunity::store() time) exactly the way CRMForecastingService's own methods already do.
 */
class PipelineService
{
    protected LeadStatusService $statusService;

    public function __construct(LeadStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Get opportunities grouped by status (stage).
     */
    public function getByStage(?string $pipelineId = null, ?int $ownerId = null, ?int $companyId = null): array
    {
        $query = Opportunity::query();

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($ownerId) {
            $query->where('owner_id', $ownerId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $opportunities = $query->get();

        $stages = [];
        foreach (LeadStatusService::VALID_STATUSES as $status) {
            $stages[$status] = $opportunities
                ->where('status', $status)
                ->values()
                ->toArray();
        }

        return $stages;
    }

    /**
     * Get pipeline visualization data.
     */
    public function getPipelineData(?string $pipelineId = null, ?int $ownerId = null, ?int $companyId = null): array
    {
        $opportunities = Opportunity::query();

        if ($pipelineId) {
            $opportunities->where('pipeline_id', $pipelineId);
        }

        if ($ownerId) {
            $opportunities->where('owner_id', $ownerId);
        }

        if ($companyId) {
            $opportunities->where('tenant_id', $companyId);
        }

        $opportunities = $opportunities->get();

        $stages = [];
        $totalValue = 0;
        $totalCount = 0;

        foreach (LeadStatusService::VALID_STATUSES as $status) {
            $stageOpps = $opportunities->where('status', $status);

            $stageValue = $stageOpps->sum(function ($opp) {
                return (float) $opp->amount;
            });

            $stages[$status] = [
                'count' => $stageOpps->count(),
                'value' => $stageValue,
                'average_value' => $stageOpps->count() > 0 ? $stageValue / $stageOpps->count() : 0,
                'opportunities' => $stageOpps->values(),
            ];

            if (!in_array($status, ['won', 'lost'])) {
                $totalValue += $stageValue;
                $totalCount += $stageOpps->count();
            }
        }

        return [
            'stages' => $stages,
            'pipeline_value' => $totalValue,
            'opportunity_count' => $totalCount,
            'won_count' => $opportunities->where('status', 'won')->count(),
            'lost_count' => $opportunities->where('status', 'lost')->count(),
        ];
    }

    /**
     * Calculate conversion rates between stages.
     */
    public function getConversionRates(?string $pipelineId = null, ?int $companyId = null): array
    {
        $query = Opportunity::query();

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $opportunities = $query->get();

        $rates = [];
        $statuses = LeadStatusService::VALID_STATUSES;

        foreach (range(0, count($statuses) - 2) as $i) {
            $currentStatus = $statuses[$i];
            $nextStatus = $statuses[$i + 1];

            $currentCount = $opportunities->where('status', $currentStatus)->count();
            $transitioned = $opportunities
                ->where('status', '>=', $nextStatus)
                ->count();

            $rate = $currentCount > 0 ? ($transitioned / $currentCount) * 100 : 0;
            $rates["{$currentStatus}_to_{$nextStatus}"] = round($rate, 2);
        }

        return $rates;
    }

    /**
     * Get revenue forecast based on pipeline.
     */
    public function getRevenueForecast(?string $pipelineId = null, ?int $companyId = null): array
    {
        $query = Opportunity::where('status', 'won');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $wonOpportunitiesValue = $query->sum(DB::raw('CAST(amount AS DECIMAL(15,2))'));

        // Calculate pipeline forecast (opportunity amount * probability)
        $query = Opportunity::where('status', '!=', 'won')
            ->where('status', '!=', 'lost');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $pipelineOpportunities = $query->get();

        $forecastedRevenue = $pipelineOpportunities->sum(function ($opp) {
            $probability = $opp->probability ?? 50;
            return ((float) $opp->amount * $probability) / 100;
        });

        return [
            'won_revenue' => (float) $wonOpportunitiesValue,
            'forecasted_revenue' => round($forecastedRevenue, 2),
            'total_potential_revenue' => round((float) $wonOpportunitiesValue + $forecastedRevenue, 2),
        ];
    }

    /**
     * Get pipeline velocity - average time in each stage.
     */
    public function getPipelineVelocity(?string $pipelineId = null, ?int $companyId = null): array
    {
        $statuses = LeadStatusService::VALID_STATUSES;
        $velocity = [];

        foreach ($statuses as $status) {
            // Find all transitions TO this status
            $logs = LeadStatusLog::where('to_status', $status);

            if ($companyId) {
                // crm_lead_status_logs has no tenant/company column of its own — it only ever
                // makes sense in the context of its owning Opportunity, so scope through that
                // real, already-tenant-scoped relation rather than inventing a new column here.
                $logs->whereHas('opportunity', fn ($q) => $q->where('tenant_id', $companyId));
            }

            $daysInStage = $logs
                ->leftJoin('crm_lead_status_logs as next_log', function ($join) {
                    $join->on('crm_lead_status_logs.opportunity_id', '=', 'next_log.opportunity_id')
                        ->whereRaw('next_log.created_at > crm_lead_status_logs.created_at')
                        ->whereRaw('next_log.created_at = (
                            SELECT MIN(created_at) FROM crm_lead_status_logs l2
                            WHERE l2.opportunity_id = crm_lead_status_logs.opportunity_id
                            AND l2.created_at > crm_lead_status_logs.created_at
                        )');
                })
                ->selectRaw('AVG(DATEDIFF(COALESCE(next_log.created_at, NOW()), crm_lead_status_logs.created_at)) as avg_days')
                ->value('avg_days');

            $velocity[$status] = (int) ($daysInStage ?? 0);
        }

        return $velocity;
    }

    /**
     * Get opportunities by owner with pipeline summary.
     */
    public function getByOwner(int $ownerId, ?string $pipelineId = null, ?int $companyId = null): array
    {
        $query = Opportunity::where('owner_id', $ownerId);

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $opportunities = $query->get();

        $byStage = [];
        foreach (LeadStatusService::VALID_STATUSES as $status) {
            $byStage[$status] = $opportunities
                ->where('status', $status)
                ->values()
                ->toArray();
        }

        return [
            'owner_id' => $ownerId,
            'by_stage' => $byStage,
            'total_value' => $opportunities->sum(function ($opp) {
                return in_array($opp->status, ['won']) ? (float) $opp->amount : 0;
            }),
            'pipeline_value' => $opportunities->sum(function ($opp) {
                return !in_array($opp->status, ['won', 'lost']) ? (float) $opp->amount : 0;
            }),
            'opportunity_count' => $opportunities->count(),
        ];
    }

    /**
     * Get stalled opportunities (not moved in X days).
     */
    public function getStalledOpportunities(?string $pipelineId = null, int $days = 30, ?int $companyId = null): Collection
    {
        $stalledDate = now()->subDays($days);

        $query = Opportunity::where('updated_at', '<', $stalledDate)
            ->where('status', '!=', 'won')
            ->where('status', '!=', 'lost');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        return $query->get();
    }

    /**
     * Get opportunities nearing close date.
     */
    public function getClosingOpportunities(?string $pipelineId = null, int $days = 7, ?int $companyId = null): Collection
    {
        $endDate = now()->addDays($days);
        $startDate = now();

        $query = Opportunity::whereBetween('expected_close_date', [$startDate, $endDate])
            ->where('status', '!=', 'won')
            ->where('status', '!=', 'lost');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        return $query->get();
    }

    /**
     * Get win/loss analysis.
     */
    public function getWinLossAnalysis(?string $pipelineId = null, ?int $companyId = null): array
    {
        $query = Opportunity::whereIn('status', ['won', 'lost']);

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $opportunities = $query->get();

        $won = $opportunities->where('status', 'won');
        $lost = $opportunities->where('status', 'lost');

        $totalCount = $won->count() + $lost->count();

        return [
            'win_count' => $won->count(),
            'loss_count' => $lost->count(),
            'win_rate' => $totalCount > 0 ? round(($won->count() / $totalCount) * 100, 2) : 0,
            'win_value' => (float) $won->sum('amount'),
            'loss_value' => (float) $lost->sum('amount'),
            'average_won_value' => $won->count() > 0 ? round($won->sum('amount') / $won->count(), 2) : 0,
            'average_lost_value' => $lost->count() > 0 ? round($lost->sum('amount') / $lost->count(), 2) : 0,
        ];
    }

    /**
     * Get stage distribution (count and value).
     */
    public function getStageDistribution(?string $pipelineId = null, ?int $companyId = null): array
    {
        $query = Opportunity::query();

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($companyId) {
            $query->where('tenant_id', $companyId);
        }

        $opportunities = $query->get();

        $distribution = [];
        foreach (LeadStatusService::VALID_STATUSES as $status) {
            $stageOpps = $opportunities->where('status', $status);
            $distribution[$status] = [
                'count' => $stageOpps->count(),
                'percentage' => $opportunities->count() > 0
                    ? round(($stageOpps->count() / $opportunities->count()) * 100, 2)
                    : 0,
                'value' => (float) $stageOpps->sum('amount'),
            ];
        }

        return $distribution;
    }
}
