<?php

declare(strict_types=1);

namespace Modules\BI\Services\AI;

use Modules\Core\Services\AI\AIService;

class BiAIService
{
    public function __construct(private readonly AIService $ai) {}

    public function generateInsights(array $kpis, string $period = 'last 30 days'): string
    {
        return $this->ai->analyzeData(
            array_merge(['period' => $period], ['kpis' => $kpis]),
            'analyst'
        );
    }

    public function detectTrends(array $kpiHistory): array
    {
        $response = $this->ai->ask(
            'Analyze these KPI historical values and identify significant trends, inflection points, and anomalies. Return valid JSON only: {"trends":[{"kpi":"...","direction":"up|down|stable","change_percent":X,"notable_events":["..."],"forecast":"..."}]}',
            ['history' => json_encode($kpiHistory)],
            'BI'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['trends' => [], 'raw' => $response];
    }

    public function suggestKpis(string $industry, array $existingKpis = []): array
    {
        $response = $this->ai->ask(
            'Suggest the most impactful KPIs for this industry that are not already tracked. Return valid JSON only: {"suggestions":[{"name":"...","metric":"...","category":"financial|operational|customer|hr","rationale":"...","formula":"..."}]}',
            ['industry' => $industry, 'existing' => json_encode($existingKpis)],
            'BI'
        );
        $decoded = json_decode($response, true);

        return $decoded ?? ['suggestions' => [], 'raw' => $response];
    }

    public function recommendDashboard(int $userId, array $usageHistory, array $availableKpis): array
    {
        $prompt = "Based on user's dashboard usage history and role, recommend the optimal dashboard configuration. Return JSON with: recommended_widgets (array of {kpi_id, position, priority, reason}), layout_suggestion (string), personalization_notes (string).";
        $result = $this->ai->ask($prompt, ['usage' => json_encode($usageHistory), 'kpis' => json_encode($availableKpis)], 'BI', 'en');

        return ['recommendations' => $result, 'user_id' => $userId];
    }
}
