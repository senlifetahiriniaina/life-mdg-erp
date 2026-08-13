<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Helpdesk\Models\CsatSurvey;

class CsatReportService
{
    /**
     * % CSAT = responses with score >= 4 / total responses
     */
    public function getCsatScore(Carbon $from, Carbon $to): float
    {
        $total = CsatSurvey::query()
            ->whereNotNull('score')
            ->whereBetween('responded_at', [$from, $to])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $positive = CsatSurvey::query()
            ->whereNotNull('score')
            ->where('score', '>=', 4)
            ->whereBetween('responded_at', [$from, $to])
            ->count();

        return round(($positive / $total) * 100, 1);
    }

    /**
     * Week-by-week evolution over the last 12 weeks
     *
     * @return array<int, array{week: string, score: float, responses: int}>
     */
    public function getWeeklyTrend(): array
    {
        $trend = [];

        for ($i = 11; $i >= 0; $i--) {
            $weekStart = Carbon::now()->startOfWeek()->subWeeks($i);
            $weekEnd = (clone $weekStart)->endOfWeek();

            $total = CsatSurvey::query()
                ->whereNotNull('score')
                ->whereBetween('responded_at', [$weekStart, $weekEnd])
                ->count();

            $positive = $total > 0
                ? CsatSurvey::query()
                    ->where('score', '>=', 4)
                    ->whereBetween('responded_at', [$weekStart, $weekEnd])
                    ->count()
                : 0;

            $trend[] = [
                'week' => $weekStart->format('d/m'),
                'score' => $total > 0 ? round(($positive / $total) * 100, 1) : 0.0,
                'responses' => $total,
            ];
        }

        return $trend;
    }

    /**
     * Average score per agent
     *
     * @return array<int, array{agent_id: int, agent_name: string, avg_score: float, responses: int}>
     */
    public function getByAgent(): array
    {
        return CsatSurvey::query()
            ->select(
                'agent_id',
                DB::raw('COUNT(*) as responses'),
                DB::raw('AVG(score) as avg_score'),
                DB::raw('SUM(CASE WHEN score >= 4 THEN 1 ELSE 0 END) as positive_count'),
            )
            ->whereNotNull('score')
            ->whereNotNull('agent_id')
            ->with('agent:id,name')
            ->groupBy('agent_id')
            ->get()
            ->map(function ($row) {
                $responses = (int) $row->getAttribute('responses');
                $positiveCount = (int) $row->getAttribute('positive_count');
                $avgScore = (float) $row->getAttribute('avg_score');

                return [
                    'agent_id' => $row->agent_id,
                    'agent_name' => $row->agent?->name ?? 'Unknown',
                    'avg_score' => round($avgScore, 2),
                    'responses' => $responses,
                    'csat_pct' => $responses > 0
                        ? round($positiveCount / $responses * 100, 1)
                        : 0.0,
                ];
            })
            ->sortByDesc('csat_pct')
            ->values()
            ->toArray();
    }

    /**
     * Distribution of scores 1-5
     *
     * @return array<int, array{score: int, count: int, pct: float}>
     */
    public function getScoreDistribution(): array
    {
        $counts = CsatSurvey::query()
            ->select('score', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('score')
            ->groupBy('score')
            ->pluck('cnt', 'score')
            ->toArray();

        $total = array_sum($counts);

        return array_map(function (int $score) use ($counts, $total) {
            $count = (int) ($counts[$score] ?? 0);

            return [
                'score' => $score,
                'count' => $count,
                'pct' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            ];
        }, [1, 2, 3, 4, 5]);
    }

    /**
     * Recent comments (negative first)
     *
     * @return array<int, array{id: int, score: int, comment: string, responded_at: string, ticket_id: int}>
     */
    public function getRecentComments(int $limit = 20): array
    {
        return CsatSurvey::query()
            ->whereNotNull('comment')
            ->whereNotNull('score')
            ->orderByRaw('score ASC')
            ->orderByDesc('responded_at')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'score' => $s->score,
                'comment' => $s->comment,
                'responded_at' => $s->responded_at?->toISOString(),
                'ticket_id' => $s->ticket_id,
            ])
            ->toArray();
    }
}
