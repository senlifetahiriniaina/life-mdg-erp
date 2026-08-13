<?php

namespace Modules\Strategy\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyRitualSession;
use Modules\Strategy\Models\StrategySignal;

class AiStrategyAdvisorService
{
    private const MODEL      = 'claude-sonnet-4-6';
    private const CACHE_TTL  = 300; // 5 minutes

    private const FALLBACK_INSIGHTS = [
        'insights' => [
            'Aligner les objectifs stratégiques avec les KPIs opérationnels pour maximiser l\'impact.',
            'Prioriser les initiatives à fort impact sur le chiffre d\'affaires et la satisfaction client.',
            'Automatiser le suivi des KPIs pour réduire le temps de reporting.',
        ],
        'risks' => [
            'Risque de dispersion si trop d\'objectifs simultanés.',
            'Manque de visibilité sur l\'avancement des équipes terrain.',
        ],
        'opportunities' => [
            'Expansion vers de nouveaux marchés africains avec les outils OHADA.',
            'Digitalisation des processus pour gains de productivité.',
        ],
    ];

    public function getInsights(string $tenantId, int $planId): array
    {
        $cacheKey = "strategy.insights.{$tenantId}.{$planId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId, $planId) {
            $plan    = StrategyPlan::with(['pillars', 'objectives.keyResults'])->find($planId);
            $signals = StrategySignal::forTenant($tenantId)->active()->limit(5)->get();

            if (!$plan) {
                return self::FALLBACK_INSIGHTS;
            }

            $apiKey = config('services.anthropic.key');
            if (!$apiKey) {
                return self::FALLBACK_INSIGHTS;
            }

            $planSummary = [
                'name'         => $plan->name,
                'framework'    => $plan->framework,
                'health_score' => $plan->health_score,
                'objectives'   => $plan->objectives->count(),
                'status'       => $plan->status,
            ];

            $systemPrompt = "Tu es un conseiller stratégique expert en OKR, BSC, et planification d'entreprise pour des marchés africains et asiatiques. Tu analyses les plans stratégiques et fournis des insights concrets et actionnables en français.";

            $userMessage = "Analyse ce plan stratégique et donne 3 insights prioritaires, 2 risques, et 2 opportunités :\n\n" . json_encode($planSummary, JSON_UNESCAPED_UNICODE) . "\n\nSignaux détectés : " . $signals->pluck('title')->implode(', ') . "\n\nRéponds en JSON avec les clés: insights[], risks[], opportunities[]";

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 800,
                    'system'     => [
                        ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'messages' => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

                if ($response->successful()) {
                    $content = $response->json('content.0.text', '');
                    // Extract JSON from response
                    preg_match('/\{.*\}/s', $content, $matches);
                    if (!empty($matches[0])) {
                        $decoded = json_decode($matches[0], true);
                        if ($decoded && isset($decoded['insights'])) {
                            return $decoded;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to fallback
            }

            return self::FALLBACK_INSIGHTS;
        });
    }

    public function getRecommendations(string $tenantId, string $context): array
    {
        $cacheKey = 'strategy.recommendations.' . md5($tenantId . $context);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($context) {
            $apiKey = config('services.anthropic.key');
            if (!$apiKey) {
                return [
                    'recommendations' => [
                        'Définir des OKRs clairs pour chaque équipe.',
                        'Mettre en place des rituels hebdomadaires de suivi.',
                        'Connecter les KPIs aux objectifs stratégiques.',
                    ],
                ];
            }

            $systemPrompt = "Tu es un expert en stratégie d'entreprise pour les marchés africains et asiatiques. Fournis des recommandations pratiques et adaptées au contexte.";

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 600,
                    'system'     => [
                        ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'messages' => [
                        ['role' => 'user', 'content' => "Contexte: {$context}\n\nDonne 3 recommandations stratégiques en JSON: {recommendations: []}"],
                    ],
                ]);

                if ($response->successful()) {
                    $content = $response->json('content.0.text', '');
                    preg_match('/\{.*\}/s', $content, $matches);
                    if (!empty($matches[0])) {
                        $decoded = json_decode($matches[0], true);
                        if ($decoded) {
                            return $decoded;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall through
            }

            return ['recommendations' => ['Définir des OKRs clairs.', 'Suivre les KPIs hebdomadairement.', 'Aligner les équipes sur la vision.']];
        });
    }

    /**
     * Generate executive board report in given language.
     */
    public function generateBoardReport(int $planId, string $locale = 'fr', string $format = 'narrative'): string
    {
        $plan = StrategyPlan::with(['pillars', 'objectives.keyResults'])->findOrFail($planId);

        $cacheKey = "strategy.board_report.{$planId}.{$locale}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($plan, $locale) {
            $apiKey = config('services.anthropic.key');

            $planData = [
                'name'         => $plan->name,
                'framework'    => $plan->framework,
                'health_score' => $plan->health_score,
                'period'       => $plan->period_start . '-' . $plan->period_end,
                'status'       => $plan->status,
                'objectives'   => $plan->objectives->map(fn ($o) => [
                    'title'    => $o->title,
                    'progress' => $o->progress,
                    'status'   => $o->status,
                ])->toArray(),
            ];

            if (!$apiKey) {
                return $this->buildFallbackBoardReport($planData, $locale);
            }

            $langInstruction = $locale === 'fr' ? 'en français' : 'in English';
            $systemPrompt    = "Tu es un expert en communication stratégique. Tu génères des rapports de direction clairs et synthétiques {$langInstruction}.";

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 1200,
                    'system'     => [
                        ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'messages' => [
                        ['role' => 'user', 'content' => "Génère un rapport de direction {$langInstruction} pour ce plan stratégique :\n\n" . json_encode($planData, JSON_UNESCAPED_UNICODE)],
                    ],
                ]);

                if ($response->successful()) {
                    return $response->json('content.0.text', $this->buildFallbackBoardReport($planData, $locale));
                }
            } catch (\Throwable $e) {
                // Fall through
            }

            return $this->buildFallbackBoardReport($planData, $locale);
        });
    }

    private function buildFallbackBoardReport(array $planData, string $locale): string
    {
        if ($locale === 'fr') {
            return "## Rapport Stratégique — {$planData['name']}\n\n**Période:** {$planData['period']}\n**Score de santé:** {$planData['health_score']}%\n**Statut:** {$planData['status']}\n\n### Objectifs stratégiques\n\nLe plan compte " . count($planData['objectives']) . " objectifs stratégiques en cours d'exécution.\n\n*Rapport généré automatiquement par WideHalo Strategy Intelligence.*";
        }
        return "## Strategic Report — {$planData['name']}\n\n**Period:** {$planData['period']}\n**Health Score:** {$planData['health_score']}%\n**Status:** {$planData['status']}\n\n### Strategic Objectives\n\nThe plan has " . count($planData['objectives']) . " strategic objectives in execution.\n\n*Report auto-generated by WideHalo Strategy Intelligence.*";
    }

    /**
     * Generate a concise summary of a ritual session's decisions and action items.
     */
    public function generateRitualSummary(int $sessionId): string
    {
        $session = StrategyRitualSession::with('ritual')->findOrFail($sessionId);

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            $decisionsCount   = count($session->decisions ?? []);
            $actionItemsCount = count($session->action_items ?? []);
            return "Session complétée avec {$decisionsCount} décision(s) et {$actionItemsCount} action(s) à suivre.";
        }

        $content = json_encode([
            'ritual'       => $session->ritual?->name,
            'decisions'    => $session->decisions ?? [],
            'action_items' => $session->action_items ?? [],
        ], JSON_UNESCAPED_UNICODE);

        $systemPrompt = 'Tu es un assistant stratégique. Génère des résumés concis de réunions en français.';

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => self::MODEL,
                'max_tokens' => 400,
                'system'     => [
                    ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'messages' => [
                    ['role' => 'user', 'content' => "Résume cette session stratégique en 3-4 phrases clés :\n\n{$content}"],
                ],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text', 'Résumé non disponible.');
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return 'Session complétée. Résumé non disponible (IA non configurée).';
    }

    /**
     * Help formulate an OKR based on pillar context and draft objective.
     */
    public function helpFormulateOkr(string $pillarContext, string $objectiveDraft, string $locale = 'fr'): array
    {
        $cacheKey = 'strategy.okr_formulation.' . md5($pillarContext . $objectiveDraft . $locale);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($pillarContext, $objectiveDraft, $locale) {
            $fallback = [
                'enabled'           => false,
                'refined_objective' => $objectiveDraft,
                'suggested_key_results' => [
                    'Augmenter le KPI principal de 20% d\'ici la fin du trimestre.',
                    'Atteindre un taux de satisfaction de 90%.',
                    'Réduire le délai moyen de livraison de 15%.',
                ],
                'tips' => [
                    'Un bon objectif est inspirant et ambitieux mais atteignable.',
                    'Les Key Results doivent être mesurables et avec une échéance claire.',
                ],
            ];

            $apiKey = config('services.anthropic.key');
            if (!$apiKey) {
                return $fallback;
            }

            $langInstruction = $locale === 'fr' ? 'en français' : 'in English';
            $systemPrompt    = "Tu es un expert en OKR (Objectives & Key Results). Tu aides à formuler des objectifs clairs et mesurables {$langInstruction}.";

            try {
                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 600,
                    'system'     => [
                        ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                    ],
                    'messages' => [
                        ['role' => 'user', 'content' => "Pilier stratégique : {$pillarContext}\nObjectif brouillon : {$objectiveDraft}\n\nAméliore cet objectif et suggère 3 Key Results mesurables. Réponds en JSON : {refined_objective, suggested_key_results[], tips[]}"],
                    ],
                ]);

                if ($response->successful()) {
                    $content = $response->json('content.0.text', '');
                    preg_match('/\{.*\}/s', $content, $matches);
                    if (!empty($matches[0])) {
                        $decoded = json_decode($matches[0], true);
                        if ($decoded && isset($decoded['refined_objective'])) {
                            return array_merge(['enabled' => true], $decoded);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall through
            }

            return $fallback;
        });
    }
}
