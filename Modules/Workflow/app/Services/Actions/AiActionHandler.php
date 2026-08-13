<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiActionHandler — Phase 39
 *
 * Handles workflow actions that invoke Claude AI:
 * analyze, classify, suggest, translate, summarize.
 *
 * Uses claude-sonnet-4-6 with prompt caching (cache_control: ephemeral).
 * Fallback-first: when ANTHROPIC_API_KEY is absent, returns static fallback.
 */
class AiActionHandler
{
    private const MODEL = 'claude-sonnet-4-6';
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const CACHE_TTL = 300; // 5 minutes

    /** @var array<string> */
    private const SUPPORTED_LANGUAGES = ['fr', 'en', 'es', 'pt', 'ar', 'sw', 'mg', 'ha', 'zh', 'hi'];

    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'ai.analyze'   => $this->analyze($params, $context),
            'ai.classify'  => $this->classify($params, $context),
            'ai.suggest'   => $this->suggest($params, $context),
            'ai.translate' => $this->translate($params, $context),
            'ai.summarize' => $this->summarize($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown AI action: {$action}"],
        };
    }

    /**
     * action: ai.analyze
     * Run Claude analysis on input data, return a structured insight.
     *
     * @param  array<string,mixed>  $params   e.g. ['prompt' => '...', 'output_format' => 'json']
     * @param  array<string,mixed>  $context
     * @return array{insight: string, status: string, enabled: bool}
     */
    public function analyze(array $params, array $context): array
    {
        $input  = $params['input'] ?? json_encode(array_diff_key($context, array_flip(['tenant_id'])));
        $prompt = $params['prompt'] ?? 'Analysez ces données ERP et fournissez une analyse structurée en 3 points.';
        $locale = $params['locale'] ?? ($context['locale'] ?? 'fr');

        return $this->callClaude(
            systemPrompt: "Vous êtes un assistant ERP expert. Répondez en {$locale}.",
            userMessage: "{$prompt}\n\nDonnées: {$input}",
            fallback: ['insight' => 'Analyse non disponible (clé API absente).', 'status' => 'fallback', 'enabled' => false]
        );
    }

    /**
     * action: ai.classify
     * Classify text or data into predefined categories.
     *
     * @param  array<string,mixed>  $params   e.g. ['text' => '...', 'categories' => ['urgent', 'normal', 'low']]
     * @param  array<string,mixed>  $context
     * @return array{category: string, confidence: float|null, status: string}
     */
    public function classify(array $params, array $context): array
    {
        $text       = $params['text'] ?? ($context['subject'] ?? $context['message'] ?? '');
        $categories = $params['categories'] ?? ['urgent', 'normal', 'low'];
        $locale     = $params['locale'] ?? 'fr';

        $categoriesStr = implode(', ', $categories);

        return $this->callClaude(
            systemPrompt: "Classifiez le texte fourni dans l'une des catégories suivantes: {$categoriesStr}. Répondez uniquement avec le nom de la catégorie.",
            userMessage: $text,
            fallback: ['category' => $categories[0] ?? 'unknown', 'confidence' => null, 'status' => 'fallback', 'enabled' => false]
        );
    }

    /**
     * action: ai.suggest
     * Get a contextual AI suggestion for the current workflow context.
     *
     * @param  array<string,mixed>  $params   e.g. ['module' => 'CRM', 'action' => 'create_contact']
     * @param  array<string,mixed>  $context
     * @return array{suggestion: string, next_actions: array<string>, status: string}
     */
    public function suggest(array $params, array $context): array
    {
        $module  = $params['module'] ?? 'ERP';
        $action  = $params['action'] ?? 'unknown';
        $locale  = $params['locale'] ?? 'fr';
        $input   = json_encode(array_diff_key($context, array_flip(['tenant_id', 'user_id'])));

        return $this->callClaude(
            systemPrompt: "Vous êtes un assistant ERP pour le module {$module}. Suggérez la meilleure action suivante en {$locale}. Soyez concis (1-2 phrases).",
            userMessage: "Action actuelle: {$action}. Contexte: {$input}",
            fallback: ['suggestion' => 'Continuez avec le flux standard.', 'next_actions' => [], 'status' => 'fallback', 'enabled' => false]
        );
    }

    /**
     * action: ai.translate
     * Translate text to the target language (10 supported languages).
     *
     * @param  array<string,mixed>  $params   e.g. ['text' => '...', 'target_lang' => 'sw']
     * @param  array<string,mixed>  $context
     * @return array{translated_text: string, target_lang: string, status: string}
     */
    public function translate(array $params, array $context): array
    {
        $text       = $params['text'] ?? ($context['message'] ?? '');
        $targetLang = $params['target_lang'] ?? 'fr';
        $sourceLang = $params['source_lang'] ?? 'fr';

        if (! in_array($targetLang, self::SUPPORTED_LANGUAGES, true)) {
            return [
                'status' => 'error',
                'reason' => "Unsupported language '{$targetLang}'. Supported: " . implode(', ', self::SUPPORTED_LANGUAGES),
            ];
        }

        if (empty($text)) {
            return ['translated_text' => '', 'target_lang' => $targetLang, 'status' => 'skipped', 'reason' => 'Empty text'];
        }

        $languageNames = [
            'fr' => 'français', 'en' => 'English', 'es' => 'español', 'pt' => 'português',
            'ar' => 'العربية', 'sw' => 'Kiswahili', 'mg' => 'Malagasy', 'ha' => 'Hausa',
            'zh' => '中文', 'hi' => 'हिन्दी',
        ];

        $targetName = $languageNames[$targetLang] ?? $targetLang;

        return $this->callClaude(
            systemPrompt: "Traduisez le texte suivant en {$targetName}. Retournez uniquement la traduction, sans explication.",
            userMessage: $text,
            fallback: ['translated_text' => $text, 'target_lang' => $targetLang, 'status' => 'fallback', 'enabled' => false]
        );
    }

    /**
     * action: ai.summarize
     * Summarize long text or data into 2-3 sentences.
     *
     * @param  array<string,mixed>  $params   e.g. ['text' => '...', 'max_sentences' => 3]
     * @param  array<string,mixed>  $context
     * @return array{summary: string, status: string}
     */
    public function summarize(array $params, array $context): array
    {
        $text         = $params['text'] ?? json_encode(array_diff_key($context, array_flip(['tenant_id'])));
        $maxSentences = (int) ($params['max_sentences'] ?? 3);
        $locale       = $params['locale'] ?? ($context['locale'] ?? 'fr');

        return $this->callClaude(
            systemPrompt: "Résumez le texte suivant en {$maxSentences} phrases maximum en {$locale}.",
            userMessage: $text,
            fallback: ['summary' => 'Résumé non disponible.', 'status' => 'fallback', 'enabled' => false]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Call Claude API with prompt caching. Falls back gracefully when API key is absent.
     *
     * @param  array<string,mixed>  $fallback
     * @return array<string,mixed>
     */
    private function callClaude(string $systemPrompt, string $userMessage, array $fallback): array
    {
        $apiKey = config('services.anthropic.key');

        if (! $apiKey) {
            return array_merge($fallback, ['enabled' => false, 'status' => 'fallback']);
        }

        $cacheKey = 'ai_action_' . md5($systemPrompt . $userMessage);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($apiKey, $systemPrompt, $userMessage, $fallback) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'x-api-key'         => $apiKey,
                        'anthropic-version' => '2023-06-01',
                        'Content-Type'      => 'application/json',
                    ])
                    ->post(self::API_URL, [
                        'model'      => self::MODEL,
                        'max_tokens' => 500,
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

                if ($response->successful()) {
                    $text = $response->json('content.0.text') ?? '';
                    return ['result' => $text, 'status' => 'success', 'enabled' => true];
                }

                Log::warning('AiActionHandler: Claude API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return array_merge($fallback, ['enabled' => false, 'status' => 'api_error']);
            } catch (\Throwable $e) {
                Log::warning('AiActionHandler: Claude API exception', ['error' => $e->getMessage()]);
                return array_merge($fallback, ['enabled' => false, 'status' => 'exception']);
            }
        });
    }
}
