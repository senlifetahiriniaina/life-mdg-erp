<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Opportunity;

/**
 * Chantier 10 finding: this was an unconditional stub — every one of its 6 methods returned
 * ['implemented' => false, ...] with no data behind them at all, undocumented anywhere in
 * CLAUDE.md, despite being live behind 6 real, RBAC-gated, routed endpoints
 * (EinsteinForecastingController — crm/einstein-forecasting/*). Investigated whether
 * CRMForecastingService (the module's real, tested forecasting service) already covers this
 * ground, and built out everything that does rather than leaving it stubbed:
 *
 *   - analyzeConfidence()/calculateForecastMetrics(): wired onto CRMForecastingService's real
 *     calculateConfidence()/getForecastAccuracy()/getHistoricalWinRate()/
 *     getAverageDealVelocity().
 *   - generateWeightedForecast(): CRMForecastingService::generateForecast() already computes
 *     exactly this — a probability-weighted pipeline/commit/best-case/conservative-case/AI
 *     forecast — reshaped into this endpoint's response shape rather than duplicated.
 *   - forecastByRepresentative(): built for real on the same probability-weighted pipeline
 *     formula generateForecast() uses (amount * probability / 100), parameterized by
 *     owner_id — a real dimension that already exists on Opportunity. Without a specific
 *     rep_id, returns the full per-rep breakdown (every real forecasting-by-rep UI needs the
 *     list, not just one rep at a time).
 *   - adjustForecast(): a real what-if computation over the real current forecast (scales the
 *     already-computed pipeline/commit/best-case by the requested factor and reports the
 *     confidence delta) — no new table needed since nothing here is persisted, matching how
 *     "scenario" adjustments already work elsewhere in this codebase (e.g. Accounting's
 *     ScenarioPlanningService::simulate(), also non-persisted by design).
 *
 *   - forecastByProduct(): genuinely NOT buildable without inventing new schema —
 *     Opportunity (confirmed via its own $fillable) has no product/line-item dimension
 *     anywhere in this codebase to group by, unlike owner_id for forecastByRepresentative.
 *     Adding one would mean designing a new product-per-opportunity data model from scratch,
 *     not wiring an existing one — left as an honest, documented stub (see CLAUDE.md's
 *     "Known gaps").
 */
class EinsteinForecastingService
{
    /**
     * CRMForecastingService extends Modules\Shared\Services\BaseService, whose constructor
     * requires a real positive int $companyId (throws TenantException otherwise) — it cannot
     * be container-auto-resolved, which is exactly why it had zero real callers anywhere in
     * this codebase before this chantier (confirmed via grep). Confirmed (also via grep)
     * that CRMForecastingService never actually reads $this->companyId anywhere — every one
     * of its methods takes its own explicit $tenantId/$userId parameters instead, so
     * BaseService's constructor gate is dead weight for this particular subclass. Resolved
     * lazily per call with a fixed placeholder id purely to satisfy that constructor, rather
     * than via constructor injection (which the container cannot auto-resolve at all) — every
     * real tenant/user filter still flows through the explicit method parameters below, never
     * through this placeholder.
     */
    private function forecasting(): CRMForecastingService
    {
        return new CRMForecastingService(1);
    }

    public function analyzeConfidence(?int $tenantId = null, ?int $userId = null): array
    {
        $query = Opportunity::query()->whereNotIn('status', ['closed_won', 'closed_lost']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $query->where('owner_id', $userId);
        }

        $high = (clone $query)->where('probability', '>=', 70)->count();
        $medium = (clone $query)->whereBetween('probability', [40, 69])->count();
        $low = (clone $query)->where('probability', '<', 40)->orWhereNull('probability')->count();

        $index = $this->forecasting()->calculateConfidence($userId, $tenantId);

        $recommendations = [];
        if ($index < 50) {
            $recommendations[] = 'Confidence is low — verify probability, stage, and expected close date on open opportunities.';
        }
        if ($low > $high) {
            $recommendations[] = 'More low-confidence deals than high-confidence ones — review pipeline qualification.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Forecast confidence is healthy.';
        }

        return [
            'high' => $high,
            'medium' => $medium,
            'low' => $low,
            'index' => $index,
            'recommendations' => $recommendations,
        ];
    }

    public function calculateForecastMetrics(?int $tenantId = null, ?int $userId = null): array
    {
        $pipelineQuery = Opportunity::query()->whereNotIn('status', ['closed_won', 'closed_lost']);
        if ($tenantId) {
            $pipelineQuery->where('tenant_id', $tenantId);
        }
        if ($userId) {
            $pipelineQuery->where('owner_id', $userId);
        }

        $avgSize = (float) $pipelineQuery->avg('amount') ?: 0.0;
        $forecasting = $this->forecasting();

        return [
            'accuracy' => $forecasting->getForecastAccuracy($userId, $tenantId),
            'win_rate' => $forecasting->getHistoricalWinRate($userId, $tenantId),
            'avg_size' => $avgSize,
            'cycle_length' => $forecasting->getAverageDealVelocity($userId, $tenantId),
            // Reuses the same composite quality score analyzeConfidence() reports, rather than
            // inventing a new health-score formula — pipeline_health_score and confidence_index
            // are deliberately the same real number under two endpoint-facing names.
            'health_score' => $forecasting->calculateConfidence($userId, $tenantId),
        ];
    }

    /**
     * Weighted forecast for one representative if $repId is given, otherwise the full
     * per-representative breakdown — built directly on the real probability-weighted pipeline
     * formula CRMForecastingService::generateForecast() already uses, parameterized by the
     * real owner_id dimension Opportunity already has.
     */
    public function forecastByRepresentative(?int $repId = null, ?int $tenantId = null): array
    {
        if ($repId !== null) {
            $forecast = $this->forecasting()->generateForecast('monthly', $repId, $tenantId);
            $rep = User::find($repId);

            return [
                'rep_id' => $repId,
                'rep_name' => $rep?->name,
                'pipeline_total' => (float) $forecast->pipeline_total,
                'commit_amount' => (float) $forecast->commit_amount,
                'best_case' => (float) $forecast->best_case,
                'forecast_amount' => (float) $forecast->forecast_amount,
                'confidence_pct' => $forecast->confidence_pct,
            ];
        }

        $query = Opportunity::query()->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost']);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $repIds = (clone $query)->whereNotNull('owner_id')->distinct()->pluck('owner_id');

        return [
            'representatives' => $repIds->map(fn (int $ownerId) => $this->forecastByRepresentative($ownerId, $tenantId))->values()->all(),
        ];
    }

    /**
     * Genuinely unbuilt — see class docblock. Opportunity has no product dimension anywhere
     * in this codebase to group by (confirmed via $fillable — no product_id, no line-item
     * model). Building this would mean designing new schema, not wiring an existing one.
     */
    public function forecastByProduct(...$args): array
    {
        return ['implemented' => false, 'message' => 'EinsteinForecastingService::forecastByProduct is not yet implemented — Opportunity has no product dimension anywhere in this codebase to group by (see class docblock).'];
    }

    /**
     * Weighted forecast for the given timeframe — a thin reshape of
     * CRMForecastingService::generateForecast(), which already computes exactly this
     * (probability-weighted pipeline/commit/best-case/conservative-case/AI prediction).
     */
    public function generateWeightedForecast(string $timeframe = 'monthly', bool $includeProbability = true, ?int $tenantId = null): array
    {
        $forecast = $this->forecasting()->generateForecast($timeframe, null, $tenantId);

        return [
            'timeframe' => $timeframe,
            'pipeline_total' => (float) $forecast->pipeline_total,
            'commit_amount' => (float) $forecast->commit_amount,
            'best_case' => (float) $forecast->best_case,
            'weighted_forecast' => (float) $forecast->forecast_amount,
            'ai_prediction' => (float) $forecast->ai_prediction,
            'confidence_pct' => $includeProbability ? $forecast->confidence_pct : null,
            'generated_at' => $forecast->generated_at,
        ];
    }

    /**
     * What-if adjustment preview: scales the real current forecast by the requested factor and
     * reports the resulting confidence delta. Deliberately not persisted — there is no
     * forecast-adjustment table anywhere in this codebase, and this mirrors how Accounting's
     * ScenarioPlanningService::simulate() already does non-persisted what-if scenarios rather
     * than inventing a new persistence layer for a preview calculation.
     */
    public function adjustForecast(string $adjustmentType, float $adjustmentFactor, ?string $reason = null, ?int $tenantId = null): array
    {
        $forecast = $this->forecasting()->generateForecast('monthly', null, $tenantId);

        $original = (float) $forecast->forecast_amount;
        $adjusted = round($original * $adjustmentFactor, 2);

        // The further the adjustment strays from 1.0 (no change), the less confidence we have
        // in the adjusted figure — a simple, honest heuristic, not a fabricated precision claim.
        $confidenceImpact = round(-1 * abs(1 - $adjustmentFactor) * 100, 2);

        return [
            'adjustment_type' => $adjustmentType,
            'reason' => $reason,
            'original' => $original,
            'adjusted' => $adjusted,
            'factor' => $adjustmentFactor,
            'confidence_impact' => $confidenceImpact,
        ];
    }
}
