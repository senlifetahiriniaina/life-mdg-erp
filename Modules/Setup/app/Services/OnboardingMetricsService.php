<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Setup\Models\FunnelSnapshot;
use Modules\Setup\Models\OnboardingSession;
use Modules\Setup\Models\OnboardingStepEvent;

/**
 * OnboardingMetricsService
 *
 * Central service for recording and querying onboarding funnel data.
 * Supports the "Simplicity First" principle by measuring how many users
 * complete onboarding in < 5 minutes.
 *
 * All write operations are intentionally simple (no queued jobs) so that
 * the tracking layer never adds latency to the user-facing wizard.
 */
class OnboardingMetricsService
{
    // -----------------------------------------------------------------------
    // Write operations
    // -----------------------------------------------------------------------

    /**
     * Start a new onboarding session.
     * Call this when the user lands on Step 1 of the wizard.
     */
    public function startSession(int $tenantId, int $userId, string $sourceType): OnboardingSession
    {
        return OnboardingSession::create([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'source_type'  => $sourceType,
            'started_at'   => Carbon::now(),
            'current_step' => 1,
        ]);
    }

    /**
     * Record a step-level event (started, completed, back, skipped, error).
     * Also advances current_step on the session when a step is completed.
     */
    public function recordStep(
        OnboardingSession $session,
        int $step,
        string $event,
        int $durationSeconds = 0,
        array $metadata = [],
    ): void {
        OnboardingStepEvent::create([
            'tenant_id'              => $session->tenant_id,
            'user_id'                => $session->user_id,
            'onboarding_session_id'  => $session->id,
            'step'                   => $step,
            'event'                  => $event,
            'duration_seconds'       => $durationSeconds > 0 ? $durationSeconds : null,
            'metadata'               => !empty($metadata) ? $metadata : null,
        ]);

        // Advance the wizard progress counter when a step is completed
        if ($event === 'completed' && $step >= $session->current_step) {
            $session->update(['current_step' => min($step + 1, 5)]);
        }
    }

    /**
     * Mark the session as successfully completed and record import outcome.
     */
    public function completeSession(
        OnboardingSession $session,
        int $rowsImported,
        bool $aiUsed,
        ?float $aiAcceptedPercent,
    ): void {
        $session->complete($rowsImported, $aiUsed, $aiAcceptedPercent);
    }

    /**
     * Mark the session as abandoned (user left the wizard early).
     */
    public function abandonSession(OnboardingSession $session, int $atStep): void
    {
        $session->abandon($atStep);
    }

    // -----------------------------------------------------------------------
    // Read operations
    // -----------------------------------------------------------------------

    /**
     * Returns funnel statistics for a tenant over the last $days days.
     *
     * Shape:
     * [
     *   total_sessions        => int,
     *   completion_rate       => float,   // 0–100
     *   avg_duration_min      => float|null,
     *   median_duration_min   => float|null,
     *   under_5_min_rate      => float,   // % of completed sessions ≤ 300s
     *   step_funnel           => [        // completion rate per step (0–100)
     *     ['step' => 1, 'completion_rate' => float],
     *     ...
     *   ],
     *   ai_adoption_rate      => float,
     *   top_source_types      => [
     *     ['source_type' => string, 'count' => int],
     *     ...
     *   ],
     * ]
     *
     * @return array<string, mixed>
     */
    public function getStats(int $tenantId, int $days = 30): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();

        $sessions = OnboardingSession::forTenant($tenantId)
            ->where('started_at', '>=', $since)
            ->get();

        $total = $sessions->count();

        if ($total === 0) {
            return $this->emptyStats();
        }

        $completed  = $sessions->filter(fn (OnboardingSession $s) => $s->completed_at !== null);
        $abandoned  = $sessions->filter(fn (OnboardingSession $s) => $s->abandoned_at !== null);

        $completionRate = round(($completed->count() / $total) * 100, 2);

        // Duration stats (completed sessions only)
        $durations = $completed
            ->whereNotNull('total_duration_seconds')
            ->pluck('total_duration_seconds')
            ->sort()
            ->values();

        $avgDurationMin    = $durations->isNotEmpty()
            ? round($durations->avg() / 60, 2)
            : null;
        $medianDurationMin = $durations->isNotEmpty()
            ? round($this->median($durations) / 60, 2)
            : null;

        $under5MinRate = $completed->isNotEmpty()
            ? round(
                ($completed->filter(fn (OnboardingSession $s) => $s->isCompletedUnder5Min())->count()
                    / $completed->count()) * 100,
                2,
            )
            : 0.0;

        // Per-step funnel: for each step, what % of sessions that reached it also completed it?
        $stepFunnel = $this->computeStepFunnel($tenantId, $since, $total);

        // AI adoption
        $aiAdoptionRate = $total > 0
            ? round(($sessions->where('ai_mapping_used', true)->count() / $total) * 100, 2)
            : 0.0;

        // Source-type distribution
        $topSourceTypes = $sessions
            ->groupBy('source_type')
            ->map(fn (Collection $g, string $type) => ['source_type' => $type, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'total_sessions'      => $total,
            'sessions_completed'  => $completed->count(),
            'sessions_abandoned'  => $abandoned->count(),
            'completion_rate'     => $completionRate,
            'avg_duration_min'    => $avgDurationMin,
            'median_duration_min' => $medianDurationMin,
            'under_5_min_rate'    => $under5MinRate,
            'step_funnel'         => $stepFunnel,
            'ai_adoption_rate'    => $aiAdoptionRate,
            'top_source_types'    => $topSourceTypes,
        ];
    }

    /**
     * Generate (or replace) the daily funnel snapshot for a tenant.
     * Called by a scheduled command once per day.
     */
    public function generateDailySnapshot(int $tenantId, Carbon $date): FunnelSnapshot
    {
        $start = $date->copy()->startOfDay();
        $end   = $date->copy()->endOfDay();

        $sessions = OnboardingSession::forTenant($tenantId)
            ->whereBetween('started_at', [$start, $end])
            ->get();

        $total     = $sessions->count();
        $completed = $sessions->filter(fn (OnboardingSession $s) => $s->completed_at !== null);
        $abandoned = $sessions->filter(fn (OnboardingSession $s) => $s->abandoned_at !== null);

        $durations = $completed
            ->whereNotNull('total_duration_seconds')
            ->pluck('total_duration_seconds')
            ->sort()
            ->values();

        $avgDuration    = $durations->isNotEmpty() ? (int) round($durations->avg()) : null;
        $medianDuration = $durations->isNotEmpty() ? (int) round($this->median($durations)) : null;

        $stepRates = $total > 0
            ? $this->computeStepFunnel($tenantId, $start, $total, $end)
            : array_fill(0, 5, null);

        $aiAdoptionRate = $total > 0
            ? round(($sessions->where('ai_mapping_used', true)->count() / $total) * 100, 2)
            : null;

        $data = [
            'tenant_id'                => $tenantId,
            'snapshot_date'            => $date->toDateString(),
            'sessions_started'         => $total,
            'sessions_completed'       => $completed->count(),
            'sessions_abandoned'       => $abandoned->count(),
            'avg_duration_seconds'     => $avgDuration,
            'median_duration_seconds'  => $medianDuration,
            'step1_completion_rate'    => $stepRates[0] ?? null,
            'step2_completion_rate'    => $stepRates[1] ?? null,
            'step3_completion_rate'    => $stepRates[2] ?? null,
            'step4_completion_rate'    => $stepRates[3] ?? null,
            'step5_completion_rate'    => $stepRates[4] ?? null,
            'ai_mapping_adoption_rate' => $aiAdoptionRate,
            'created_at'               => Carbon::now(),
        ];

        return FunnelSnapshot::updateOrCreate(
            ['tenant_id' => $tenantId, 'snapshot_date' => $date->toDateString()],
            $data,
        );
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Compute per-step completion rates.
     * For step N: % of all sessions that had at least one 'completed' event for step N.
     *
     * @return array<int, float|null>  Indexed 0-4 (step 1-5)
     */
    private function computeStepFunnel(int $tenantId, Carbon $since, int $total, ?Carbon $until = null): array
    {
        if ($total === 0) {
            return array_fill(0, 5, null);
        }

        $query = OnboardingStepEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event', 'completed')
            ->where('created_at', '>=', $since);

        if ($until !== null) {
            $query->where('created_at', '<=', $until);
        }

        // Count distinct sessions per step that have a 'completed' event
        $counts = $query
            ->select('step', DB::raw('COUNT(DISTINCT onboarding_session_id) as cnt'))
            ->groupBy('step')
            ->pluck('cnt', 'step');

        $rates = [];
        for ($step = 1; $step <= 5; $step++) {
            $cnt       = (int) ($counts->get($step) ?? 0);
            $rates[]   = round(($cnt / $total) * 100, 2);
        }

        return $rates;
    }

    /**
     * Compute the median of a sorted collection of numbers.
     *
     * @param Collection<int, int|float> $sortedValues
     */
    private function median(Collection $sortedValues): float
    {
        $count = $sortedValues->count();
        if ($count === 0) {
            return 0.0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (float) $sortedValues->get($middle);
        }

        return ($sortedValues->get($middle - 1) + $sortedValues->get($middle)) / 2;
    }

    /**
     * Empty stats shape — returned when there are no sessions in the period.
     *
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'total_sessions'      => 0,
            'sessions_completed'  => 0,
            'sessions_abandoned'  => 0,
            'completion_rate'     => 0.0,
            'avg_duration_min'    => null,
            'median_duration_min' => null,
            'under_5_min_rate'    => 0.0,
            'step_funnel'         => array_map(
                fn (int $s) => ['step' => $s, 'completion_rate' => 0.0],
                range(1, 5),
            ),
            'ai_adoption_rate'    => 0.0,
            'top_source_types'    => [],
        ];
    }
}
