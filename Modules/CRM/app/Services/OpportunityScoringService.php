<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\CRM\Models\EngagementSignal;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityScore;
use Modules\CRM\Models\ScoringRule;

class OpportunityScoringService
{
    /**
     * Score a single opportunity using all active rules + engagement signals.
     * Stores or updates the crm_opportunity_scores record.
     */
    public function score(Opportunity $opportunity): OpportunityScore
    {
        $rules = ScoringRule::where('is_active', true)->orderBy('sort_order')->get();
        $signals = $opportunity->engagementSignals()
            ->where('occurred_at', '>=', now()->subDays(90))
            ->get();

        [$engagement, $fit, $velocity, $history] = $this->computeSubScores(
            $opportunity, $rules, $signals
        );

        $total = min(100, $engagement + $fit + $velocity + $history);
        $winProbability = $this->computeWinProbability($opportunity, $total, $signals);
        $grade = $this->totalToGrade($total);

        $breakdown = [
            'engagement' => $engagement,
            'fit' => $fit,
            'velocity' => $velocity,
            'history' => $history,
            'total' => $total,
        ];

        $record = OpportunityScore::updateOrCreate(
            ['opportunity_id' => $opportunity->id],
            [
                'total_score' => $total,
                'grade' => $grade,
                'engagement_score' => $engagement,
                'fit_score' => $fit,
                'velocity_score' => $velocity,
                'history_score' => $history,
                'win_probability' => $winProbability,
                'score_breakdown' => $breakdown,
                'signals_used' => $signals->pluck('signal_type')->unique()->values()->toArray(),
                'scored_at' => now(),
            ]
        );

        return $record;
    }

    /**
     * Score all open opportunities in bulk (for scheduled job).
     */
    public function scoreAll(): array
    {
        $results = ['scored' => 0, 'errors' => []];

        Opportunity::whereIn('stage', ['prospecting', 'qualification', 'proposal', 'negotiation'])
            ->each(function (Opportunity $opp) use (&$results) {
                try {
                    $this->score($opp);
                    $results['scored']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = ['id' => $opp->id, 'error' => $e->getMessage()];
                }
            });

        return $results;
    }

    /**
     * Record an engagement signal for an opportunity and re-score.
     */
    public function recordSignal(
        Opportunity $opportunity,
        string $signalType,
        ?int $activityId = null,
        ?string $description = null,
        ?Carbon $occurredAt = null
    ): EngagementSignal {
        $scoreImpact = $this->signalImpact($signalType);

        $signal = EngagementSignal::create([
            'opportunity_id' => $opportunity->id,
            'signal_type' => $signalType,
            'score_impact' => $scoreImpact,
            'description' => $description,
            'source' => 'manual',
            'occurred_at' => $occurredAt ?? now(),
            'activity_id' => $activityId,
        ]);

        // Re-score after new signal
        $this->score($opportunity);

        return $signal;
    }

    /**
     * Get the current score or compute it if missing.
     */
    public function getScore(Opportunity $opportunity): OpportunityScore
    {
        $existing = $opportunity->score;

        // Re-score if older than 24h or missing
        if (! $existing || $existing->scored_at->diffInHours(now()) > 24) {
            return $this->score($opportunity);
        }

        return $existing;
    }

    /**
     * Return leaderboard: top N opportunities by score.
     */
    public function leaderboard(int $limit = 20): Collection
    {
        return OpportunityScore::with('opportunity.account')
            ->orderByDesc('total_score')
            ->limit($limit)
            ->get()
            ->map(fn (OpportunityScore $s) => [
                'opportunity_id' => $s->opportunity_id,
                'opportunity_name' => $s->opportunity?->name,
                'account_name' => $s->opportunity?->account?->name,
                'stage' => $s->opportunity?->stage,
                'amount' => $s->opportunity?->amount,
                'total_score' => $s->total_score,
                'grade' => $s->grade,
                'win_probability' => (float) $s->win_probability,
                'action' => $s->getRecommendedAction(),
                'scored_at' => $s->scored_at->toIso8601String(),
            ]);
    }

    /**
     * Pipeline forecast: aggregate weighted_amount by probability across all scored opps.
     */
    public function pipelineForecast(): array
    {
        $scores = OpportunityScore::with('opportunity')->get();

        $committed = $scores->where('win_probability', '>=', 0.7)
            ->sum(fn ($s) => (float) ($s->opportunity?->amount ?? 0) * (float) $s->win_probability);

        $upside = $scores->whereBetween('win_probability', [0.4, 0.69])
            ->sum(fn ($s) => (float) ($s->opportunity?->amount ?? 0) * (float) $s->win_probability);

        $pipeline = $scores->where('win_probability', '>', 0)
            ->sum(fn ($s) => (float) ($s->opportunity?->amount ?? 0));

        return [
            'committed_forecast' => round($committed, 2),
            'upside_forecast' => round($upside, 2),
            'pipeline_total' => round($pipeline, 2),
            'opportunities' => $scores->count(),
            'avg_win_probability' => $scores->count() > 0
                ? round($scores->avg('win_probability'), 2)
                : 0,
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * @param  Collection<int, ScoringRule>  $rules
     * @param  Collection<int, EngagementSignal>  $signals
     * @return array<int, int>
     */
    private function computeSubScores(
        Opportunity $opportunity,
        Collection $rules,
        Collection $signals
    ): array {
        $engagement = $this->scoreCategory($opportunity, $rules->where('category', 'engagement'), $signals);
        $fit = $this->scoreCategory($opportunity, $rules->where('category', 'fit'), $signals);
        $velocity = $this->scoreCategory($opportunity, $rules->where('category', 'velocity'), $signals);
        $history = $this->scoreCategory($opportunity, $rules->where('category', 'history'), $signals);

        // Always add base engagement from signals
        $signalBonus = min(25, (int) $signals->sum('score_impact'));

        return [
            min(25, $engagement + $signalBonus),
            min(25, $fit),
            min(25, $velocity),
            min(25, $history),
        ];
    }

    /**
     * @param  Collection<int, ScoringRule>  $rules
     * @param  Collection<int, EngagementSignal>  $signals
     */
    private function scoreCategory(Opportunity $opp, Collection $rules, Collection $signals): int
    {
        $total = 0;

        foreach ($rules as $rule) {
            $fieldValue = $this->extractField($opp, $rule->condition_field, $signals);
            $total += $rule->evaluate($fieldValue) * $rule->weight;
        }

        return max(0, $total);
    }

    /**
     * @param  Collection<int, EngagementSignal>  $signals
     */
    private function extractField(Opportunity $opp, string $field, Collection $signals): mixed
    {
        return match ($field) {
            'deal_size' => (float) ($opp->amount ?? 0),
            'stage' => $opp->stage ?? '',
            'activities_count' => $opp->activities()->count(),
            'days_in_stage' => now()->diffInDays($opp->updated_at),
            'email_opens' => $signals->where('signal_type', 'email_open')->count(),
            'meetings_count' => $signals->where('signal_type', 'meeting_attended')->count(),
            'demo_requested' => $signals->contains('signal_type', 'demo_requested'),
            'proposal_viewed' => $signals->contains('signal_type', 'proposal_viewed'),
            'close_date_days' => $opp->expected_close_date ? now()->diffInDays(Carbon::parse($opp->expected_close_date), false) : null,
            'probability' => $opp->probability ?? 0,
            default => null,
        };
    }

    /**
     * @param  Collection<int, EngagementSignal>  $signals
     */
    private function computeWinProbability(Opportunity $opp, int $totalScore, Collection $signals): float
    {
        // Base from total score
        $base = $totalScore / 100;

        // Stage multipliers (later stages = higher probability)
        $stageMultiplier = match ($opp->stage ?? '') {
            'prospecting' => 0.5,
            'qualification' => 0.65,
            'proposal' => 0.75,
            'negotiation' => 0.85,
            'closed_won' => 1.0,
            'closed_lost' => 0.0,
            default => 0.6,
        };

        // Recent engagement boost (last 7 days)
        $recentSignals = $signals->filter(fn ($s) => $s->occurred_at->gt(now()->subDays(7)))->count();
        $engagementBoost = min(0.10, $recentSignals * 0.02);

        return round(min(1.0, $base * $stageMultiplier + $engagementBoost), 4);
    }

    private function totalToGrade(int $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 65 => 'B',
            $score >= 50 => 'C',
            $score >= 35 => 'D',
            default => 'F',
        };
    }

    private function signalImpact(string $signalType): int
    {
        return match ($signalType) {
            'demo_requested' => 20,
            'contract_sent' => 18,
            'proposal_viewed' => 15,
            'meeting_attended' => 12,
            'inbound_call' => 10,
            'call_completed' => 8,
            'email_reply' => 7,
            'document_downloaded' => 6,
            'website_visit' => 4,
            'email_open' => 2,
            default => 5,
        };
    }
}
