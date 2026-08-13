<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Setup\Data\TargetSchemas;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\SourceSchema;

/**
 * AiMappingService
 *
 * Uses the Claude API (claude-sonnet-4-6) to suggest field mappings between
 * source ERP columns and WideHalo target schemas.
 *
 * AI Assisted First principle: every field-mapping friction point gets a
 * Claude suggestion with confidence score. Degrades gracefully when
 * ANTHROPIC_API_KEY is not configured.
 *
 * Prompt caching: system prompt + target schema = cached block.
 * Source schema = uncached (varies per job).
 */
class AiMappingService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = (string) env('ANTHROPIC_API_KEY', '');
        $this->model  = (string) config('setup.ai_model', 'claude-sonnet-4-6');
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Request AI field mapping suggestions for an ImportJob.
     * Returns an empty array if the API key is not configured (graceful degradation).
     *
     * @return list<array{
     *   source_field: string,
     *   target_field: string,
     *   target_table: string,
     *   transform_type: string,
     *   transform_config: array<string,mixed>|null,
     *   confidence: float
     * }>
     */
    public function suggestMappings(ImportJob $job, SourceSchema $schema): array
    {
        if ($this->apiKey === '') {
            return [];
        }

        try {
            $messages = $this->buildMappingPrompt($schema, $job->target_module, $job->target_entity);

            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta'    => 'prompt-caching-2024-07-31',
                'content-type'      => 'application/json',
            ])->timeout(60)->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 4096,
                'system'     => $this->buildSystemPrompt($job->target_module, $job->target_entity),
                'messages'   => $messages,
            ]);

            if (!$response->successful()) {
                Log::warning('AiMappingService: API returned error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $responseBody = $response->json();
            $content      = $responseBody['content'][0]['text'] ?? '';

            $suggestions = $this->parseMappingResponse($content);

            // Persist AI metadata on the job
            if (!empty($suggestions)) {
                $avgConfidence = array_sum(array_column($suggestions, 'confidence')) / count($suggestions);
                $job->update([
                    'ai_mapping_used'       => true,
                    'ai_mapping_confidence' => round($avgConfidence, 2),
                ]);
            }

            return $suggestions;
        } catch (\Throwable $e) {
            Log::warning('AiMappingService: exception during mapping suggestion', [
                'message' => $e->getMessage(),
                'job_id'  => $job->id,
            ]);
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Prompt building
    // -----------------------------------------------------------------------

    /**
     * Build the messages array for the Claude API request.
     * The user message contains the source schema (uncached).
     *
     * @return list<array<string,mixed>>
     */
    public function buildMappingPrompt(SourceSchema $schema, string $targetModule, string $targetEntity): array
    {
        $targetSchema  = $this->getTargetSchema($targetModule, $targetEntity);
        $targetJson    = json_encode($targetSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $sourceColumns = $schema->detected_columns ?? [];
        $sourceJson    = json_encode($sourceColumns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $userContent = <<<TEXT
Here is the source schema extracted from the import file:

<source_schema>
{$sourceJson}
</source_schema>

Here is the WideHalo target schema for {$targetModule}/{$targetEntity}:

<target_schema>
{$targetJson}
</target_schema>

Please suggest mappings. Return a JSON array only — no other text.
TEXT;

        return [
            [
                'role'    => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $userContent,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the system prompt (candidate for prompt caching — stable content).
     *
     * @return list<array<string,mixed>>
     */
    private function buildSystemPrompt(string $targetModule, string $targetEntity): array
    {
        $instructions = <<<SYSTEM
You are an expert ERP data migration assistant for WideHalo ERP, an Africa First / Asia First enterprise resource planning system.

Your task is to map source ERP column names to WideHalo's target schema fields for the module "{$targetModule}" entity "{$targetEntity}".

Rules:
1. For each source column, suggest the best matching WideHalo target field.
2. Assign a confidence score between 0.0 and 1.0.
3. If no match exists, omit the source field from the output.
4. Suggest the appropriate transform_type:
   - "direct" — value can be used as-is
   - "date_format" — date string needs reformatting; include transform_config.format (source format) and transform_config.target (target format, always "Y-m-d")
   - "number_format" — numeric value needs cleaning (currency symbols, thousands separator)
   - "lookup" — value needs mapping through a dictionary; include transform_config.map
   - "concat" — combine multiple source fields; include transform_config.fields (array) and transform_config.separator
   - "split" — take one part of a split string; include transform_config.delimiter and transform_config.index (0-based)
5. Return ONLY a valid JSON array. No markdown, no explanation.

Output format (JSON array):
[
  {
    "source_field": "NOM CLIENT",
    "target_field": "last_name",
    "target_table": "crm_contacts",
    "transform_type": "direct",
    "transform_config": null,
    "confidence": 0.92
  },
  ...
]
SYSTEM;

        return [
            [
                'type' => 'text',
                'text' => $instructions,
                // Mark as cacheable — stable system instructions
                'cache_control' => ['type' => 'ephemeral'],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Response parsing
    // -----------------------------------------------------------------------

    /**
     * Parse the JSON mapping array from Claude's response text.
     *
     * @return list<array{source_field: string, target_field: string, target_table: string, transform_type: string, transform_config: array<string,mixed>|null, confidence: float}>
     */
    public function parseMappingResponse(string $response): array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', trim($response));
        $cleaned = trim((string) $cleaned);

        // Find first JSON array
        $start = strpos($cleaned, '[');
        $end   = strrpos($cleaned, ']');

        if ($start === false || $end === false) {
            return [];
        }

        $json = substr($cleaned, $start, $end - $start + 1);

        try {
            $parsed = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($parsed)) {
            return [];
        }

        $validated = [];
        foreach ($parsed as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (empty($item['source_field']) || empty($item['target_field'])) {
                continue;
            }

            $validated[] = [
                'source_field'    => (string) $item['source_field'],
                'target_field'    => (string) $item['target_field'],
                'target_table'    => (string) ($item['target_table'] ?? ''),
                'transform_type'  => (string) ($item['transform_type'] ?? 'direct'),
                'transform_config'=> isset($item['transform_config']) && is_array($item['transform_config'])
                    ? $item['transform_config']
                    : null,
                'confidence'      => (float) ($item['confidence'] ?? 0.5),
            ];
        }

        return $validated;
    }

    // -----------------------------------------------------------------------
    // Target schema lookup
    // -----------------------------------------------------------------------

    /**
     * Return WideHalo field definitions for the target module + entity.
     *
     * @return list<array<string,mixed>>
     */
    public function getTargetSchema(string $module, string $entity): array
    {
        return TargetSchemas::getSchema($module, $entity);
    }
}
