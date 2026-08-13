<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Calls the Claude API with ratio snapshots + benchmark data
 * to generate strategic recommendations.
 */
class StrategyAIService
{
    private string $apiKey;
    private string $model;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey  = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        $this->model   = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->enabled = $this->apiKey !== '';
    }

    /**
     * Generate strategic recommendations from ratio + benchmark data.
     *
     * @param  array<string, mixed> $ratioData   Current ratio values with status
     * @param  array<string, mixed> $context      e.g. ['tenant' => ..., 'industry' => ...]
     * @param  string $locale
     * @return array{enabled: bool, recommendations: array, priorities: array, risks: array, opportunities: array}
     */
    public function recommend(array $ratioData, array $context = [], string $locale = 'fr'): array
    {
        $cacheKey = 'strategy_ai_recommend:' . md5(json_encode([$ratioData, $context, $locale]));

        return Cache::remember($cacheKey, 300, function () use ($ratioData, $context, $locale) {
            if (!$this->enabled) {
                return $this->fallbackRecommendations($ratioData, $locale);
            }

            return $this->callClaude($ratioData, $context, $locale);
        });
    }

    private function callClaude(array $ratioData, array $context, string $locale): array
    {
        $systemPrompt = <<<'SYSTEM'
        You are WideHalo's Strategic AI Advisor, embedded in an ERP system used by African and Asian SMEs.
        Your role is to analyze financial and operational KPI ratios, compare them to industry benchmarks,
        and provide strategic recommendations in the user's language.

        Always structure your response as JSON with keys:
        - recommendations: array of {module, title, description, priority: 'high'|'medium'|'low', action: string}
        - priorities: array of {rank, module, issue, impact: string}
        - risks: array of {title, description, severity: 'critical'|'warning'|'info'}
        - opportunities: array of {title, description, module, potential_impact: string}

        Keep responses concise, practical, and actionable for a non-financial business owner.
        SYSTEM;

        $userMessage = sprintf(
            "Analyze these KPI ratios for our business (locale: %s):\n\n%s\n\nContext: %s\n\nProvide strategic recommendations in %s.",
            $locale,
            json_encode($this->summarizeRatios($ratioData), JSON_PRETTY_PRINT),
            json_encode($context),
            $locale === 'fr' ? 'French' : 'English'
        );

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->model,
                    'max_tokens' => 1500,
                    'system'     => [
                        ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'messages'   => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (!$response->successful()) {
                return $this->fallbackRecommendations($ratioData, $locale);
            }

            $content = $response->json('content.0.text', '');
            $parsed  = json_decode($content, true);

            if (!is_array($parsed)) {
                return $this->fallbackRecommendations($ratioData, $locale);
            }

            return array_merge(['enabled' => true], $parsed);
        } catch (\Exception) {
            return $this->fallbackRecommendations($ratioData, $locale);
        }
    }

    private function summarizeRatios(array $ratioData): array
    {
        $summary = [];
        foreach ($ratioData as $module => $ratios) {
            foreach ($ratios as $ratio) {
                $summary[] = [
                    'module'          => $module,
                    'name'            => $ratio['name'] ?? '',
                    'current'         => $ratio['current_value'] ?? null,
                    'benchmark'       => $ratio['benchmark_value'] ?? null,
                    'status'          => $ratio['status'] ?? 'unknown',
                    'unit'            => $ratio['unit'] ?? '',
                ];
            }
        }
        return $summary;
    }

    private function fallbackRecommendations(array $ratioData, string $locale): array
    {
        $isFr = $locale === 'fr';
        $redRatios = $this->findRedRatios($ratioData);

        $recommendations = [];
        foreach (array_slice($redRatios, 0, 3) as $r) {
            $recommendations[] = [
                'module'      => $r['module'],
                'title'       => $isFr ? "Améliorer {$r['name']}" : "Improve {$r['name']}",
                'description' => $isFr
                    ? "Le ratio {$r['name']} ({$r['current_value']} {$r['unit']}) est en dessous de la cible. Revue des processus recommandée."
                    : "The {$r['name']} ratio ({$r['current_value']} {$r['unit']}) is below target. Process review recommended.",
                'priority'    => 'high',
                'action'      => $isFr ? 'Planifier une revue avec le responsable du module' : 'Schedule a module manager review',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'module'      => 'General',
                'title'       => $isFr ? 'Performance globale satisfaisante' : 'Overall performance satisfactory',
                'description' => $isFr
                    ? 'Vos principaux ratios sont dans les normes. Concentrez-vous sur la croissance et l\'expansion.'
                    : 'Your key ratios are within norms. Focus on growth and expansion.',
                'priority'    => 'low',
                'action'      => $isFr ? 'Maintenir le cap et viser les P75 des benchmarks' : 'Stay the course and target P75 benchmarks',
            ];
        }

        return [
            'enabled'         => false,
            'recommendations' => $recommendations,
            'priorities'      => array_map(fn($r, $i) => [
                'rank'   => $i + 1,
                'module' => $r['module'],
                'issue'  => $r['name'],
                'impact' => $isFr ? 'Impact direct sur la rentabilité' : 'Direct impact on profitability',
            ], $redRatios, array_keys($redRatios)),
            'risks' => $this->computeRisks($ratioData, $isFr),
            'opportunities' => [
                [
                    'title'            => $isFr ? 'Benchmark P75' : 'P75 Benchmark Target',
                    'description'      => $isFr ? 'Atteindre le P75 des benchmarks industrie vous distinguerait de 75% de vos concurrents.' : 'Reaching P75 benchmarks would put you ahead of 75% of industry peers.',
                    'module'           => 'Strategy',
                    'potential_impact' => $isFr ? 'Avantage concurrentiel durable' : 'Sustainable competitive advantage',
                ],
            ],
        ];
    }

    private function findRedRatios(array $ratioData): array
    {
        $red = [];
        foreach ($ratioData as $module => $ratios) {
            foreach ($ratios as $ratio) {
                if (($ratio['status'] ?? '') === 'red') {
                    $red[] = array_merge($ratio, ['module' => $module]);
                }
            }
        }
        return $red;
    }

    private function computeRisks(array $ratioData, bool $isFr): array
    {
        $risks  = [];
        $redQty = count($this->findRedRatios($ratioData));

        if ($redQty >= 5) {
            $risks[] = [
                'title'       => $isFr ? 'Multiples ratios critiques' : 'Multiple critical ratios',
                'description' => $isFr ? "$redQty ratios sont en rouge. Intervention urgente recommandée." : "$redQty ratios are red. Urgent intervention recommended.",
                'severity'    => 'critical',
            ];
        } elseif ($redQty >= 2) {
            $risks[] = [
                'title'       => $isFr ? 'Ratios sous-performants' : 'Underperforming ratios',
                'description' => $isFr ? "$redQty ratios nécessitent attention." : "$redQty ratios need attention.",
                'severity'    => 'warning',
            ];
        }

        return $risks;
    }
}
