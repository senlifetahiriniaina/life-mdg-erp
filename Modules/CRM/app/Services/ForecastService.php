<?php

declare(strict_types=1);

namespace Modules\CRM\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Forecast;
use Modules\CRM\Models\Opportunity;

class ForecastService
{
    /**
     * Generate (or update) a forecast for a given period.
     *
     * Pipeline = sum(opportunities.amount * probability/100) where stage != won/lost
     * Commit   = opportunities with probability >= 70%
     * Best case = pipeline * 1.2
     * AI prediction = pipeline * 0.85 (heuristic)
     */
    public function generateForecast(string $period, ?int $userId = null): Forecast
    {
        /** @var float $pipeline */
        $pipeline = (float) (Opportunity::query()
            ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost'])
            ->when($userId, fn ($q) => $q->where('owner_id', $userId))
            ->sum(DB::raw('amount * probability / 100')) ?: 0);

        /** @var float $commit */
        $commit = (float) (Opportunity::query()
            ->whereNotIn('stage', ['won', 'closed_won', 'lost', 'closed_lost'])
            ->where('probability', '>=', 70)
            ->when($userId, fn ($q) => $q->where('owner_id', $userId))
            ->sum(DB::raw('amount * probability / 100')) ?: 0);

        return Forecast::updateOrCreate(
            ['period' => $period, 'user_id' => $userId],
            [
                'forecast_amount' => round($pipeline * 0.8, 2),
                'commit_amount' => round($commit, 2),
                'best_case' => round($pipeline * 1.2, 2),
                'pipeline_total' => round($pipeline, 2),
                'ai_prediction' => round($pipeline * 0.85, 2),
                'confidence_pct' => 78,
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Return forecasts for all periods, optionally filtered by user.
     *
     * @return Collection<int, Forecast>
     */
    public function allForecasts(?int $userId = null): Collection
    {
        return Forecast::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('period')
            ->get();
    }
}
