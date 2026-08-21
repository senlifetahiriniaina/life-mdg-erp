<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chantier 26 (volet B) — génère une courte justification en français pour
 * chacune des 3 propositions d'objectif commercial déjà calculées
 * (déterministe, voir SalesObjectiveService::proposeObjectives()). L'IA ne
 * choisit jamais le montant cible — seulement le texte explicatif — même
 * principe déjà établi ailleurs dans cette app (FinancialSimulationService,
 * CashflowForecastService) : jamais laisser l'IA halluciner un chiffre.
 *
 * Replique le patron déjà établi par
 * Modules\Analytics\Services\AiForecastNarrativeService : repli statique en
 * français si ANTHROPIC_API_KEY absente ou l'appel échoue, jamais une
 * erreur.
 */
class SalesObjectiveAiService
{
    private const MODEL         = 'claude-sonnet-4-6';
    private const MAX_TOKENS    = 500;
    private const CACHE_TTL     = 3600;
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VER = '2023-06-01';

    /**
     * @param  string  $scopeLabel  Libellé lisible du périmètre (ex. "toute l'équipe commerciale", "le commercial Jean Rakoto", "le client ACME", "la catégorie Produits finis")
     * @param  array<int, array{label: string, target_amount: float, growth_rate_percent: float|null}>  $candidates
     * @return array<int, string> basis text, même ordre que $candidates
     */
    public function generateBasisTexts(
        string $scopeLabel,
        array $candidates,
        float $historicalAverage,
        string $currency,
        string $periodLabel,
    ): array {
        $apiKey = config('ai.providers.anthropic.api_key');

        if (empty($apiKey)) {
            return $this->staticBasisTexts($candidates, $historicalAverage, $currency);
        }

        $cacheKey = 'sales_objective_basis:'.md5($scopeLabel.$periodLabel.json_encode($candidates));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $scopeLabel, $candidates, $historicalAverage, $currency, $periodLabel, $apiKey
        ) {
            try {
                return $this->callAnthropicApi($scopeLabel, $candidates, $historicalAverage, $currency, $periodLabel, $apiKey);
            } catch (\Throwable $e) {
                Log::warning('SalesObjectiveAiService: API error', ['error' => $e->getMessage()]);
                return $this->staticBasisTexts($candidates, $historicalAverage, $currency);
            }
        });
    }

    private function callAnthropicApi(
        string $scopeLabel,
        array $candidates,
        float $historicalAverage,
        string $currency,
        string $periodLabel,
        string $apiKey,
    ): array {
        $candidatesJson = json_encode($candidates, JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<PROMPT
Tu es un analyste commercial expert pour une PME africaine (Madagascar,
textile/EPI). On te donne 3 propositions de chiffre d'affaires cible déjà
calculées mathématiquement (jamais à modifier) pour un périmètre commercial
et une période donnés. Pour CHAQUE proposition, rédige une justification
courte (1-2 phrases, en français) expliquant pourquoi ce niveau
d'ambition est raisonnable compte tenu de l'historique.

Réponds strictement en JSON :
{"proposals": [{"label": "...", "basis": "..."}, ...]}

Ne change jamais les montants, ne réponds qu'avec les 3 libellés déjà donnés
dans le même ordre.
PROMPT;

        $userMessage = <<<MSG
Périmètre : {$scopeLabel}
Période : {$periodLabel}
Moyenne historique mensuelle : {$historicalAverage} {$currency}

Propositions :
{$candidatesJson}
MSG;

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VER,
            'content-type'      => 'application/json',
        ])->timeout(20)->post(self::ANTHROPIC_URL, [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => [
                ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
            ],
            'messages'   => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error: '.$response->status());
        }

        $content = $response->json('content.0.text', '');

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['proposals']) && count($decoded['proposals']) === count($candidates)) {
                return array_map(fn ($p) => (string) ($p['basis'] ?? ''), $decoded['proposals']);
            }
        }

        return $this->staticBasisTexts($candidates, $historicalAverage, $currency);
    }

    /** @return array<int, string> */
    private function staticBasisTexts(array $candidates, float $historicalAverage, string $currency): array
    {
        $avgFormatted = number_format($historicalAverage, 0, ',', ' ');

        return array_map(function (array $c) use ($avgFormatted, $currency) {
            $rate = $c['growth_rate_percent'] ?? 0;
            return match (true) {
                $rate <= 0.01 => "Basé sur la moyenne historique observée ({$avgFormatted} {$currency}/mois), sans croissance supplémentaire — objectif de maintien.",
                $rate <= 8.0  => "Moyenne historique ({$avgFormatted} {$currency}/mois) majorée d'une croissance modérée de {$rate}%, cohérente avec la tendance récente.",
                default       => "Moyenne historique ({$avgFormatted} {$currency}/mois) majorée d'une croissance ambitieuse de {$rate}%, à ne retenir que si des actions commerciales renforcées sont prévues.",
            };
        }, $candidates);
    }
}
