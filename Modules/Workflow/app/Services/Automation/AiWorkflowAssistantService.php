<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Automation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Models\Automation\AutomationExecution;
use Modules\Workflow\Models\Automation\AutomationFlow;

/**
 * AI-powered workflow assistant.
 *
 * Calls Claude API (claude-sonnet-4-6) with prompt caching.
 * Every public method degrades gracefully when the API key is absent.
 */
class AiWorkflowAssistantService
{
    private const MODEL        = 'claude-sonnet-4-6';
    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const CACHE_TTL    = 300; // 5 minutes

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Analyzes tenant module usage patterns and suggests new automations.
     *
     * @return list<array{title:string,description:string,trigger:string,estimated_time_saved:string,template_id:int|null}>
     */
    public function suggestAutomations(int $tenantId, string $locale = 'fr'): array
    {
        $cacheKey = "ai_workflow_suggestions_{$tenantId}_{$locale}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId, $locale) {
            $usageStats = $this->collectUsageStats($tenantId);

            $userMessage = sprintf(
                "Voici les statistiques d'utilisation anonymisées du locataire (tenant) :\n%s\n\n" .
                "En tant qu'expert ERP, propose 5 automatisations pertinentes en %s. " .
                "Pour chaque suggestion, fournis: title, description (1 phrase), trigger (type d'événement), " .
                "estimated_time_saved (ex: 2h/semaine). " .
                "Réponds UNIQUEMENT avec un tableau JSON valide, sans markdown ni texte supplémentaire.",
                json_encode($usageStats, JSON_UNESCAPED_UNICODE),
                $locale === 'fr' ? 'français' : 'English',
            );

            $response = $this->callClaude(
                systemPrompt: $this->getSystemPrompt('suggestions'),
                userMessage: $userMessage,
            );

            if ($response === null) {
                return $this->fallbackSuggestions($locale);
            }

            try {
                $suggestions = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($suggestions)) {
                    return $this->fallbackSuggestions($locale);
                }

                return array_map(fn ($s) => [
                    'title'               => (string) ($s['title'] ?? ''),
                    'description'         => (string) ($s['description'] ?? ''),
                    'trigger'             => (string) ($s['trigger'] ?? ''),
                    'estimated_time_saved' => (string) ($s['estimated_time_saved'] ?? ''),
                    'template_id'         => isset($s['template_id']) ? (int) $s['template_id'] : null,
                ], array_slice($suggestions, 0, 5));
            } catch (\JsonException) {
                return $this->fallbackSuggestions($locale);
            }
        });
    }

    /**
     * Analyzes recent failed executions and suggests fixes.
     *
     * @return list<array{execution_id:int,root_cause:string,suggested_fix:string,severity:string}>
     */
    public function analyzeFailures(int $tenantId): array
    {
        $cacheKey = "ai_workflow_failures_{$tenantId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            $failures = AutomationExecution::forTenant($tenantId)
                ->failed()
                ->with('flow')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($e) => [
                    'execution_id'  => $e->id,
                    'flow_name'     => $e->flow?->name ?? 'Unknown',
                    'error_node_id' => $e->error_node_id,
                    'node_results'  => $e->node_results,
                    'duration_ms'   => $e->duration_ms,
                ])
                ->toArray();

            if (empty($failures)) {
                return [];
            }

            $userMessage = sprintf(
                "Analyse ces exécutions échouées de workflows et identifie les causes racines et correctifs :\n%s\n\n" .
                "Pour chaque exécution, réponds avec: execution_id, root_cause (1 phrase), suggested_fix (action concrète), " .
                "severity (warning|error). " .
                "Réponds UNIQUEMENT avec un tableau JSON valide.",
                json_encode($failures, JSON_UNESCAPED_UNICODE),
            );

            $response = $this->callClaude(
                systemPrompt: $this->getSystemPrompt('failures'),
                userMessage: $userMessage,
            );

            if ($response === null) {
                return $this->fallbackFailureAnalysis($failures);
            }

            try {
                $results = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                return is_array($results) ? $results : $this->fallbackFailureAnalysis($failures);
            } catch (\JsonException) {
                return $this->fallbackFailureAnalysis($failures);
            }
        });
    }

    /**
     * Validates a flow definition before saving.
     *
     * @param  array<string,mixed>  $flowDefinition
     * @return array{valid:bool,warnings:list<string>,errors:list<string>,suggestions:list<string>}
     */
    public function validateFlow(array $flowDefinition): array
    {
        $cacheKey = 'ai_workflow_validate_' . md5(json_encode($flowDefinition) ?: '');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($flowDefinition) {
            $userMessage = sprintf(
                "Valide la définition de ce workflow n8n-like et identifie les problèmes :\n%s\n\n" .
                "Réponds avec un objet JSON: {valid: bool, warnings: string[], errors: string[], suggestions: string[]}. " .
                "Réponds UNIQUEMENT avec le JSON valide.",
                json_encode($flowDefinition, JSON_UNESCAPED_UNICODE),
            );

            $response = $this->callClaude(
                systemPrompt: $this->getSystemPrompt('validation'),
                userMessage: $userMessage,
            );

            if ($response === null) {
                return $this->staticValidation($flowDefinition);
            }

            try {
                $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($result) || ! isset($result['valid'])) {
                    return $this->staticValidation($flowDefinition);
                }

                return [
                    'valid'       => (bool) $result['valid'],
                    'warnings'    => (array) ($result['warnings'] ?? []),
                    'errors'      => (array) ($result['errors'] ?? []),
                    'suggestions' => (array) ($result['suggestions'] ?? []),
                ];
            } catch (\JsonException) {
                return $this->staticValidation($flowDefinition);
            }
        });
    }

    /**
     * Generates a complete flow definition from a natural language description.
     *
     * @return array<string,mixed>  complete flow_definition JSON with nodes and connections
     */
    public function generateFlowFromDescription(string $description, string $locale = 'fr'): array
    {
        $cacheKey = 'ai_workflow_generate_' . md5($description . $locale);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($description, $locale) {
            $userMessage = sprintf(
                "Génère un workflow ERP complet (format JSON n8n-like) pour cette description en %s :\n\"%s\"\n\n" .
                "Le JSON doit contenir: {name, description, trigger_type, trigger_config, nodes: [{id, label, module, action, config, position: {x,y}}], " .
                "connections: [{from_node_id, to_node_id, condition}]}. " .
                "Réponds UNIQUEMENT avec le JSON valide, sans markdown.",
                $locale === 'fr' ? 'français' : 'English',
                $description,
            );

            $response = $this->callClaude(
                systemPrompt: $this->getSystemPrompt('generation'),
                userMessage: $userMessage,
            );

            if ($response === null) {
                return $this->fallbackFlowGeneration($description, $locale);
            }

            try {
                $flow = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                return is_array($flow) ? $flow : $this->fallbackFlowGeneration($description, $locale);
            } catch (\JsonException) {
                return $this->fallbackFlowGeneration($description, $locale);
            }
        });
    }

    /**
     * Summarizes execution history in natural language for a given flow.
     */
    public function summarizeExecutions(int $flowId, string $period = 'week'): string
    {
        $cacheKey = "ai_workflow_summary_{$flowId}_{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($flowId, $period) {
            $since = match ($period) {
                'day'   => now()->subDay(),
                'month' => now()->subMonth(),
                default => now()->subWeek(),
            };

            $executions = AutomationExecution::where('flow_id', $flowId)
                ->where('created_at', '>=', $since)
                ->get();

            if ($executions->isEmpty()) {
                return $period === 'week'
                    ? 'Aucune exécution enregistrée cette semaine.'
                    : 'Aucune exécution enregistrée sur cette période.';
            }

            $total     = $executions->count();
            $completed = $executions->where('status', 'completed')->count();
            $failed    = $executions->where('status', 'failed')->count();
            $avgMs     = $executions->avg('duration_ms');
            $rate      = $total > 0 ? round($completed / $total * 100, 1) : 0;

            $stats = [
                'period'           => $period,
                'total_executions' => $total,
                'completed'        => $completed,
                'failed'           => $failed,
                'success_rate'     => $rate,
                'avg_duration_ms'  => round($avgMs ?? 0),
            ];

            $userMessage = sprintf(
                "Rédige un résumé concis en français (2-3 phrases) des statistiques d'exécution de ce workflow :\n%s",
                json_encode($stats, JSON_UNESCAPED_UNICODE),
            );

            $response = $this->callClaude(
                systemPrompt: $this->getSystemPrompt('summary'),
                userMessage: $userMessage,
            );

            return $response ?? sprintf(
                "Ce workflow a été exécuté %d fois %s avec un taux de réussite de %s%%. " .
                "%d exécution(s) ont échoué. Durée moyenne : %dms.",
                $total,
                $period === 'week' ? 'cette semaine' : "ce {$period}",
                $rate,
                $failed,
                round($avgMs ?? 0),
            );
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function callClaude(string $systemPrompt, string $userMessage): ?string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => 1200,
                'system'     => [
                    [
                        'type'          => 'text',
                        'text'          => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if ($response->failed()) {
                Log::warning('AiWorkflowAssistantService: Claude API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            /** @var array<string,mixed> $data */
            $data = $response->json();

            return (string) ($data['content'][0]['text'] ?? '');
        } catch (\Throwable $e) {
            Log::error('AiWorkflowAssistantService: request failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function getSystemPrompt(string $context): string
    {
        $base = "Tu es un expert en automatisation de processus métier pour un ERP africain (WideHalo ERP). " .
            "Tu connais les modules ERP suivants : CRM, Comptabilité, RH, Inventaire, Ventes, Achats, Production, Helpdesk, " .
            "WhatsApp, Email, SMS, Documents, Projets, Logistique, Planning, Qualité, PLM, Timesheets. " .
            "Les workflows sont de type n8n avec nodes et connections. " .
            "Les triggers sont : webhook, schedule (cron), module_event (ex: crm.contact.created), manual. " .
            "Respecte les normes OHADA et les pratiques des PME francophones africaines.";

        return match ($context) {
            'suggestions' => $base . ' Analyse les patterns d\'utilisation et propose des automations à haute valeur ajoutée.',
            'failures'    => $base . ' Analyse les logs d\'erreur et identifie les causes racines avec précision technique.',
            'validation'  => $base . ' Valide les définitions de workflows et identifie les problèmes de configuration ou de logique.',
            'generation'  => $base . ' Génère des workflows complets et cohérents à partir de descriptions en langage naturel.',
            'summary'     => $base . ' Rédige des résumés clairs et actionnables des performances d\'exécution.',
            default       => $base,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function collectUsageStats(int $tenantId): array
    {
        $flows = AutomationFlow::where('tenant_id', $tenantId)
            ->select(['trigger_type', 'tags', 'total_runs', 'success_runs', 'is_active'])
            ->take(50)
            ->get();

        $recentExecutions = AutomationExecution::forTenant($tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'active_flows'          => $flows->where('is_active', true)->count(),
            'total_flows'           => $flows->count(),
            'trigger_distribution'  => $flows->groupBy('trigger_type')->map->count()->toArray(),
            'weekly_executions'     => $recentExecutions,
            'top_tags'              => $flows->pluck('tags')->flatten()->countBy()->sortDesc()->take(5)->toArray(),
        ];
    }

    /**
     * @return list<array{title:string,description:string,trigger:string,estimated_time_saved:string,template_id:null}>
     */
    private function fallbackSuggestions(string $locale): array
    {
        if ($locale === 'fr') {
            return [
                [
                    'title'                => 'Relance automatique des factures impayées',
                    'description'          => 'Envoyer un rappel WhatsApp et email 30 jours après la date d\'échéance.',
                    'trigger'              => 'schedule: 0 9 * * *',
                    'estimated_time_saved' => '3h/semaine',
                    'template_id'          => null,
                ],
                [
                    'title'                => 'Alerte stock critique',
                    'description'          => 'Notifier le responsable achats quand le stock passe sous le seuil minimum.',
                    'trigger'              => 'module_event: inventory.stock.low',
                    'estimated_time_saved' => '2h/semaine',
                    'template_id'          => null,
                ],
                [
                    'title'                => 'Rapport hebdomadaire des performances',
                    'description'          => 'Générer et envoyer un rapport PDF des KPIs chaque lundi matin.',
                    'trigger'              => 'schedule: 0 8 * * 1',
                    'estimated_time_saved' => '4h/semaine',
                    'template_id'          => null,
                ],
                [
                    'title'                => 'Onboarding automatique d\'un nouveau client',
                    'description'          => 'Créer un dossier, envoyer un email de bienvenue et planifier un appel de suivi.',
                    'trigger'              => 'module_event: crm.contact.created',
                    'estimated_time_saved' => '1.5h/semaine',
                    'template_id'          => null,
                ],
                [
                    'title'                => 'Escalade ticket helpdesk non résolu',
                    'description'          => 'Escalader automatiquement les tickets non résolus après 24h au manager.',
                    'trigger'              => 'schedule: */30 * * * *',
                    'estimated_time_saved' => '2h/semaine',
                    'template_id'          => null,
                ],
            ];
        }

        return [
            [
                'title'                => 'Automatic overdue invoice follow-up',
                'description'          => 'Send a WhatsApp and email reminder 30 days after the due date.',
                'trigger'              => 'schedule: 0 9 * * *',
                'estimated_time_saved' => '3h/week',
                'template_id'          => null,
            ],
            [
                'title'                => 'Critical stock alert',
                'description'          => 'Notify the purchasing manager when stock falls below minimum threshold.',
                'trigger'              => 'module_event: inventory.stock.low',
                'estimated_time_saved' => '2h/week',
                'template_id'          => null,
            ],
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $failures
     * @return list<array{execution_id:int,root_cause:string,suggested_fix:string,severity:string}>
     */
    private function fallbackFailureAnalysis(array $failures): array
    {
        return array_map(fn ($f) => [
            'execution_id'  => (int) ($f['execution_id'] ?? 0),
            'root_cause'    => 'Erreur lors de l\'exécution d\'un nœud — vérifiez la configuration.',
            'suggested_fix' => 'Relancez l\'exécution et consultez les logs du nœud en erreur.',
            'severity'      => 'error',
        ], $failures);
    }

    /**
     * @param  array<string,mixed>  $flowDefinition
     * @return array{valid:bool,warnings:list<string>,errors:list<string>,suggestions:list<string>}
     */
    private function staticValidation(array $flowDefinition): array
    {
        $errors      = [];
        $warnings    = [];
        $suggestions = [];

        if (empty($flowDefinition['nodes'])) {
            $errors[] = 'Le workflow ne contient aucun nœud.';
        }

        if (empty($flowDefinition['trigger_type'])) {
            $errors[] = 'Le type de déclencheur (trigger_type) est obligatoire.';
        }

        if (! empty($flowDefinition['nodes']) && count($flowDefinition['nodes']) < 2) {
            $warnings[] = 'Un workflow avec un seul nœud n\'effectue aucune action.';
        }

        if (empty($flowDefinition['connections']) && ! empty($flowDefinition['nodes']) && count($flowDefinition['nodes']) > 1) {
            $warnings[] = 'Les nœuds ne sont pas connectés entre eux.';
        }

        if (empty($errors)) {
            $suggestions[] = 'Ajoutez un nœud de notification pour informer les équipes du résultat.';
        }

        return [
            'valid'       => empty($errors),
            'warnings'    => $warnings,
            'errors'      => $errors,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fallbackFlowGeneration(string $description, string $locale): array
    {
        return [
            'name'           => $locale === 'fr' ? 'Nouveau workflow' : 'New workflow',
            'description'    => $description,
            'trigger_type'   => 'manual',
            'trigger_config' => [],
            'nodes'          => [
                [
                    'id'       => 'trigger_1',
                    'label'    => $locale === 'fr' ? 'Déclencheur manuel' : 'Manual trigger',
                    'module'   => 'Workflow',
                    'action'   => 'manual_trigger',
                    'config'   => [],
                    'position' => ['x' => 100, 'y' => 200],
                ],
                [
                    'id'       => 'action_1',
                    'label'    => $locale === 'fr' ? 'Action à configurer' : 'Action to configure',
                    'module'   => 'Workflow',
                    'action'   => 'placeholder',
                    'config'   => [],
                    'position' => ['x' => 400, 'y' => 200],
                ],
            ],
            'connections' => [
                ['from_node_id' => 'trigger_1', 'to_node_id' => 'action_1', 'condition' => null],
            ],
            '_generated_from_description' => true,
            '_ai_available'               => false,
        ];
    }
}
