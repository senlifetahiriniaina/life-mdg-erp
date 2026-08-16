<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiForecastNarrativeService
 *
 * Generates AI-powered narrative explanations for any WideHalo forecast result.
 * Africa-aware system prompts include OHADA standards, African seasonality
 * (Ramadan, school year, harvest cycles), mobile money patterns, and XOF/XAF context.
 *
 * Falls back to static narratives when ANTHROPIC_API_KEY is absent or the API fails.
 * Every call is cached for 1 hour keyed by module+predictions hash+locale.
 */
class AiForecastNarrativeService
{
    private const MODEL            = 'claude-sonnet-4-6';
    private const MAX_TOKENS       = 600;
    private const CACHE_TTL        = 3600; // 1 hour
    private const ANTHROPIC_URL    = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VER    = '2023-06-01';

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Generate a narrative for a forecast result.
     *
     * @param  string  $module        demand|cashflow|hr|production
     * @param  array   $predictions   The forecast data array
     * @param  array   $historicalData Historical data for context
     * @param  string  $locale        User locale (fr|en|ar|sw|ha|zh|hi)
     * @param  string  $context       Optional business context (plain text)
     * @return array{
     *   enabled: bool,
     *   narrative: string,
     *   key_factors: string[],
     *   risks: string[],
     *   opportunities: string[],
     *   recommended_actions: array<array{label:string,action_key:string,module:string,priority:string}>,
     *   confidence_explanation: string
     * }
     */
    public function generateNarrative(
        string $module,
        array  $predictions,
        array  $historicalData = [],
        string $locale         = 'fr',
        string $context        = '',
    ): array {
        // Same config path as Modules\Core\Services\AI\AnthropicProvider — the
        // provider registry's canonical location, not config/services.php
        // (which has no 'anthropic' entry in this repo).
        $apiKey = config('ai.providers.anthropic.api_key');

        if (empty($apiKey)) {
            return $this->getFallbackNarrative($module, $locale);
        }

        $cacheKey = 'forecast_narrative:' . md5("{$module}:{$locale}:" . json_encode($predictions));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $module, $predictions, $historicalData, $locale, $context, $apiKey
        ) {
            try {
                return $this->callAnthropicApi(
                    $module, $predictions, $historicalData, $locale, $context, $apiKey
                );
            } catch (\Throwable $e) {
                Log::warning('AiForecastNarrativeService: API error', [
                    'module' => $module,
                    'error'  => $e->getMessage(),
                ]);
                return $this->getFallbackNarrative($module, $locale);
            }
        });
    }

    // ─── Private: Anthropic call ──────────────────────────────────────────────

    private function callAnthropicApi(
        string $module,
        array  $predictions,
        array  $historicalData,
        string $locale,
        string $context,
        string $apiKey,
    ): array {
        $systemPrompt = $this->buildSystemPrompt($module, $locale);
        $userMessage  = $this->buildUserMessage($module, $predictions, $historicalData, $context, $locale);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VER,
            'content-type'      => 'application/json',
        ])->timeout(20)->post(self::ANTHROPIC_URL, [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => [
                [
                    'type'          => 'text',
                    'text'          => $systemPrompt,
                    'cache_control' => ['type' => 'ephemeral'],
                ],
            ],
            'messages'   => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error: ' . $response->status());
        }

        $content = $response->json('content.0.text', '');

        return $this->parseAiResponse($content, $module, $locale);
    }

    // ─── System prompt ────────────────────────────────────────────────────────

    private function buildSystemPrompt(string $module, string $locale): string
    {
        $lang = match ($locale) {
            'fr'    => 'French',
            'en'    => 'English',
            'ar'    => 'Arabic',
            'sw'    => 'Swahili',
            'ha'    => 'Hausa',
            'zh'    => 'Chinese (Simplified)',
            'hi'    => 'Hindi',
            default => 'French',
        };

        return <<<PROMPT
You are an expert ERP financial analyst for WideHalo, a business management platform
serving African and Asian SMEs. You specialize in forecasting narratives for the
following Africa-first context:

ACCOUNTING & COMPLIANCE:
- OHADA/SYSCOHADA accounting standards (17 West/Central African countries)
- UEMOA and CEMAC monetary union rules
- Primary currencies: XOF (CFA Franc UEMOA), XAF (CFA Franc CEMAC), plus GHS, NGN, KES
- Mobile money payments: Orange Money, Wave, MTN MoMo, M-Pesa, Airtel Money

AFRICAN SEASONALITY & BUSINESS PATTERNS:
- Ramadan: demand surge (textiles +30-40%, food +25%), cash flow stress on payables
- Back-to-school (September/October): textiles, stationery, services peak
- Harvest season (October–December in Sahel): agricultural commodity pricing impact
- End-of-month mobile money transfers: cash flow timing patterns
- Import cycle from China: 45-90 day lead times affecting inventory

MODULE CONTEXT: {$module}
OUTPUT LANGUAGE: {$lang}

RESPONSE FORMAT (strict JSON):
{
  "narrative": "2-3 sentence business explanation in {$lang}",
  "key_factors": ["factor1", "factor2", "factor3"],
  "risks": ["risk1", "risk2", "risk3"],
  "opportunities": ["opportunity1", "opportunity2"],
  "recommended_actions": [
    {"label": "Action label", "action_key": "snake_case_key", "module": "ModuleName", "priority": "high|medium|low"}
  ],
  "confidence_explanation": "Why the confidence level is what it is"
}

Keep the narrative concise (max 3 sentences). All text must be in {$lang}.
Focus on actionable insights, not just observations.
PROMPT;
    }

    // ─── User message ─────────────────────────────────────────────────────────

    private function buildUserMessage(
        string $module,
        array  $predictions,
        array  $historicalData,
        string $context,
        string $locale,
    ): string {
        $predictionsJson  = json_encode(array_slice($predictions, 0, 30), JSON_UNESCAPED_UNICODE);
        $historicalJson   = json_encode(array_slice($historicalData, 0, 10), JSON_UNESCAPED_UNICODE);

        $moduleContext = match ($module) {
            'demand'     => 'Product demand forecast — focus on inventory management, reorder points, and seasonal drivers.',
            'cashflow'   => 'Cash flow forecast — focus on OHADA liquidity ratios, payment timing (mobile money), and working capital.',
            'hr'         => 'HR headcount forecast — focus on recruitment timeline, turnover risk, and payroll cost evolution.',
            'production' => 'Production capacity forecast — focus on bottlenecks, material shortages, and schedule optimization.',
            default      => 'General business forecast.',
        };

        return <<<MSG
MODULE: {$module}
CONTEXT: {$moduleContext}
{$context}

FORECAST DATA (next periods):
{$predictionsJson}

HISTORICAL DATA (last periods):
{$historicalJson}

Please analyze this forecast and provide a JSON response following the system prompt format.
MSG;
    }

    // ─── Parse AI response ────────────────────────────────────────────────────

    private function parseAiResponse(string $content, string $module, string $locale): array
    {
        // Extract JSON from the response (model may wrap it in markdown)
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return array_merge(['enabled' => true], $decoded);
            }
        }

        // Could not parse — return fallback
        return $this->getFallbackNarrative($module, $locale);
    }

    // ─── Static fallback narratives ───────────────────────────────────────────

    /**
     * Static fallback narratives per module and locale.
     * Covers all 4 modules in French (primary) and English.
     */
    private function getFallbackNarrative(string $module, string $locale = 'fr'): array
    {
        $isFr = str_starts_with($locale, 'fr');

        $narratives = [
            'demand' => [
                'fr' => [
                    'narrative'              => 'La demande prévisionnelle montre une tendance stable avec quelques variations saisonnières attendues. Le stock actuel devrait être réajusté pour anticiper les pics de commandes.',
                    'key_factors'            => ['Saisonnalité (Ramadan, rentrée)', 'Tendance historique des 3 derniers mois', 'Variations de prix fournisseurs'],
                    'risks'                  => ['Rupture de stock si réapprovisionnement tardif', 'Hausse des prix matières premières', 'Décalage import depuis Chine (45-90j)'],
                    'opportunities'          => ['Augmenter les stocks avant le pic Ramadan', 'Négocier des prix fermes avec fournisseurs clés'],
                    'recommended_actions'    => [
                        ['label' => 'Créer un bon de commande fournisseur', 'action_key' => 'create_purchase_order', 'module' => 'Achats', 'priority' => 'high'],
                        ['label' => 'Définir des alertes de stock', 'action_key' => 'set_stock_alerts', 'module' => 'Inventory', 'priority' => 'medium'],
                        ['label' => 'Consulter le rapport de prévision', 'action_key' => 'view_forecast_report', 'module' => 'Analytics', 'priority' => 'low'],
                    ],
                    'confidence_explanation' => 'Confiance basée sur 3 mois de données historiques avec un modèle Holt-Winters.',
                ],
                'en' => [
                    'narrative'              => 'Forecast demand shows a stable trend with expected seasonal variations. Current stock levels should be adjusted to anticipate order peaks.',
                    'key_factors'            => ['Seasonality (Ramadan, back-to-school)', 'Historical 3-month trend', 'Supplier price variations'],
                    'risks'                  => ['Stockout if replenishment delayed', 'Raw material price increase', 'Import lead time from China (45-90d)'],
                    'opportunities'          => ['Build stock before Ramadan peak', 'Negotiate firm prices with key suppliers'],
                    'recommended_actions'    => [
                        ['label' => 'Create purchase order', 'action_key' => 'create_purchase_order', 'module' => 'Achats', 'priority' => 'high'],
                        ['label' => 'Set stock alerts', 'action_key' => 'set_stock_alerts', 'module' => 'Inventory', 'priority' => 'medium'],
                        ['label' => 'View forecast report', 'action_key' => 'view_forecast_report', 'module' => 'Analytics', 'priority' => 'low'],
                    ],
                    'confidence_explanation' => 'Confidence based on 3 months of historical data using Holt-Winters model.',
                ],
            ],
            'cashflow' => [
                'fr' => [
                    'narrative'              => "La trésorerie prévisionnelle reste positive sur 90 jours mais des creux ponctuels sont à surveiller en fin de mois. Les encaissements Mobile Money représentent 40% des entrées — anticipez les délais de virement.",
                    'key_factors'            => ['Délais de paiement clients (DSO)', 'Cycles de paiement fournisseurs (DPO)', 'Encaissements Mobile Money Orange/Wave'],
                    'risks'                  => ['Creux de trésorerie en fin de mois', 'Décalage entre facturation et encaissement', 'Échéances fiscales TVA/IS'],
                    'opportunities'          => ['Activer les relances automatiques clients', 'Négocier un allongement DPO fournisseurs'],
                    'recommended_actions'    => [
                        ['label' => 'Lancer les relances clients en retard', 'action_key' => 'send_payment_reminders', 'module' => 'Accounting', 'priority' => 'high'],
                        ['label' => 'Vérifier les échéances fiscales', 'action_key' => 'check_tax_deadlines', 'module' => 'Accounting', 'priority' => 'high'],
                        ['label' => 'Renégocier délais fournisseurs', 'action_key' => 'renegotiate_payment_terms', 'module' => 'Achats', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => 'Modèle basé sur les 6 derniers mois de flux réels avec ajustement saisonnier OHADA.',
                ],
                'en' => [
                    'narrative'              => 'Projected cash flow remains positive over 90 days but end-of-month dips require monitoring. Mobile Money collections represent 40% of inflows — plan for transfer delays.',
                    'key_factors'            => ['Customer payment delays (DSO)', 'Supplier payment cycles (DPO)', 'Mobile Money Orange/Wave collections'],
                    'risks'                  => ['End-of-month cash dips', 'Invoice-to-collection timing gap', 'VAT/CIT tax deadlines'],
                    'opportunities'          => ['Activate automated customer reminders', 'Negotiate extended DPO with suppliers'],
                    'recommended_actions'    => [
                        ['label' => 'Send overdue payment reminders', 'action_key' => 'send_payment_reminders', 'module' => 'Accounting', 'priority' => 'high'],
                        ['label' => 'Check tax deadlines', 'action_key' => 'check_tax_deadlines', 'module' => 'Accounting', 'priority' => 'high'],
                        ['label' => 'Renegotiate supplier terms', 'action_key' => 'renegotiate_payment_terms', 'module' => 'Achats', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => 'Model trained on 6 months of actual cash flows with OHADA seasonal adjustment.',
                ],
            ],
            'hr' => [
                'fr' => [
                    'narrative'              => "Les prévisions d'effectifs indiquent un besoin de recrutement à court terme pour soutenir la croissance. Le taux de turnover est dans la moyenne du secteur mais quelques profils à risque méritent une attention immédiate.",
                    'key_factors'            => ["Taux de croissance de l'activité", 'Historique des départs/arrivées', 'Pics saisonniers de charge'],
                    'risks'                  => ["Départ d'un profil clé non remplacé", "Surcharge d'équipe pendant recrutement", 'Hausse de la masse salariale'],
                    'opportunities'          => ['Anticiper les besoins par la formation interne', "Fidéliser par la revalorisation salariale annuelle"],
                    'recommended_actions'    => [
                        ['label' => 'Ouvrir un poste en recrutement', 'action_key' => 'open_job_posting', 'module' => 'HR', 'priority' => 'high'],
                        ['label' => 'Programmer les entretiens de rétention', 'action_key' => 'schedule_retention_interviews', 'module' => 'HR', 'priority' => 'medium'],
                        ['label' => 'Réviser la grille salariale', 'action_key' => 'review_salary_grid', 'module' => 'HR', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => "Basé sur l'historique RH des 12 derniers mois et les tendances sectorielles.",
                ],
                'en' => [
                    'narrative'              => 'Headcount forecasts indicate short-term recruitment needs to support growth. Turnover rate is near sector average but a few at-risk profiles need immediate attention.',
                    'key_factors'            => ['Business growth rate', 'Historical departures/arrivals', 'Seasonal workload peaks'],
                    'risks'                  => ['Key profile departure without replacement', 'Team overload during recruitment', 'Payroll cost increase'],
                    'opportunities'          => ['Anticipate needs through internal training', 'Retain talent through annual salary review'],
                    'recommended_actions'    => [
                        ['label' => 'Open a recruitment position', 'action_key' => 'open_job_posting', 'module' => 'HR', 'priority' => 'high'],
                        ['label' => 'Schedule retention interviews', 'action_key' => 'schedule_retention_interviews', 'module' => 'HR', 'priority' => 'medium'],
                        ['label' => 'Review salary grid', 'action_key' => 'review_salary_grid', 'module' => 'HR', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => 'Based on 12-month HR history and sector benchmarks.',
                ],
            ],
            'production' => [
                'fr' => [
                    'narrative'              => "La capacité de production est utilisée à un niveau optimal mais la semaine suivante présente un risque de dépassement. Les goulots identifiés doivent être traités de façon préventive pour éviter les retards clients.",
                    'key_factors'            => ['Carnet de commandes confirmées', 'Disponibilité des matières premières', 'Capacité machine et main-d\'œuvre'],
                    'risks'                  => ["Dépassement capacité en semaine de pointe", 'Rupture matière première critique', 'Panne machine non planifiée'],
                    'opportunities'          => ['Optimiser le planning par lissage de charge', 'Anticiper les approvisionnements à 90 jours'],
                    'recommended_actions'    => [
                        ['label' => 'Planifier maintenance préventive', 'action_key' => 'schedule_maintenance', 'module' => 'Manufacturing', 'priority' => 'high'],
                        ['label' => 'Commander les matières critiques', 'action_key' => 'create_material_order', 'module' => 'Achats', 'priority' => 'high'],
                        ['label' => 'Ajuster le planning de production', 'action_key' => 'adjust_production_schedule', 'module' => 'Manufacturing', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => "Basé sur les ordres de fabrication confirmés et la capacité nominale des ateliers.",
                ],
                'en' => [
                    'narrative'              => 'Production capacity is at optimal utilization but the coming week shows overload risk. Identified bottlenecks must be addressed preventively to avoid customer delays.',
                    'key_factors'            => ['Confirmed order backlog', 'Raw material availability', 'Machine and labor capacity'],
                    'risks'                  => ['Capacity overrun during peak week', 'Critical material shortage', 'Unplanned machine breakdown'],
                    'opportunities'          => ['Optimize scheduling through load leveling', 'Anticipate 90-day procurement'],
                    'recommended_actions'    => [
                        ['label' => 'Schedule preventive maintenance', 'action_key' => 'schedule_maintenance', 'module' => 'Manufacturing', 'priority' => 'high'],
                        ['label' => 'Order critical materials', 'action_key' => 'create_material_order', 'module' => 'Achats', 'priority' => 'high'],
                        ['label' => 'Adjust production schedule', 'action_key' => 'adjust_production_schedule', 'module' => 'Manufacturing', 'priority' => 'medium'],
                    ],
                    'confidence_explanation' => 'Based on confirmed manufacturing orders and workshop nominal capacity.',
                ],
            ],
        ];

        $lang  = $isFr ? 'fr' : 'en';
        $entry = $narratives[$module][$lang] ?? $narratives['demand']['fr'];

        return array_merge(['enabled' => false], $entry);
    }
}
