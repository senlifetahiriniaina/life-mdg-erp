<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Natural Language to SQL Service
 * Converts user natural language queries to parameterized SQL
 * using Claude API with schema awareness.
 * Supports French, English, Spanish, Portuguese.
 */
class NlToSqlService
{
    private const MODEL = 'claude-opus-4-7';
    private const MAX_TOKENS = 500;

    /**
     * Convert natural language query to SQL
     */
    public function queryToSql(string $naturalLanguageQuery, string $locale = 'fr'): array
    {
        if (empty(config('services.anthropic.key'))) {
            return $this->getFallbackResponse($naturalLanguageQuery);
        }

        try {
            $schema = $this->getSchemaDescription();
            
            $prompt = $this->buildPrompt($naturalLanguageQuery, $schema, $locale);

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if (!$response->successful()) {
                Log::warning('Claude API error in NlToSqlService', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->getFallbackResponse($naturalLanguageQuery);
            }

            $content = $response->json('content.0.text');

            return $this->parseSqlResponse($content, $naturalLanguageQuery, $locale);
        } catch (\Exception $e) {
            Log::error('NlToSqlService error', ['error' => $e->getMessage()]);
            return $this->getFallbackResponse($naturalLanguageQuery);
        }
    }

    /**
     * Build prompt with schema for Claude
     */
    private function buildPrompt(string $query, string $schema, string $locale): string
    {
        $locale_name = match($locale) {
            'fr' => 'French',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
            default => 'English',
        };

        return <<<PROMPT
You are a SQL expert. Convert the following {$locale_name} natural language query into safe, parameterized SQL.

Available database schema:
{$schema}

Query in {$locale_name}:
"{$query}"

Respond ONLY with JSON:
{
  "sql": "SELECT ... WHERE ...",
  "params": ["value1", "value2"],
  "explanation": "What this query does",
  "safe": true|false
}

If the query is unsafe (SQL injection risk), set "safe": false and explain why.
PROMPT;
    }

    /**
     * Get database schema description
     */
    private function getSchemaDescription(): string
    {
        $tables = DB::getDoctrineSchemaManager()->listTableNames();

        $schema = "Tables available:\n\n";

        foreach (array_slice($tables, 0, 20) as $table) { // Limit to first 20 tables
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $schema .= "- {$table}: " . implode(', ', array_slice($columns, 0, 10)) . "\n";
        }

        return $schema;
    }

    /**
     * Parse Claude response
     */
    private function parseSqlResponse(string $content, string $query, string $locale): array
    {
        try {
            // Extract JSON from response (Claude might wrap it in text)
            preg_match('/\{[\s\S]*\}/', $content, $matches);
            
            if (empty($matches)) {
                return [
                    'enabled' => false,
                    'error' => 'Could not parse Claude response',
                    'query' => $query,
                ];
            }

            $parsed = json_decode($matches[0], true);

            if (!isset($parsed['sql']) || !isset($parsed['safe'])) {
                return [
                    'enabled' => false,
                    'error' => 'Invalid response format',
                    'query' => $query,
                ];
            }

            return [
                'enabled' => true,
                'sql' => $parsed['sql'],
                'params' => $parsed['params'] ?? [],
                'explanation' => $parsed['explanation'] ?? '',
                'safe' => $parsed['safe'] ?? false,
                'query' => $query,
                'locale' => $locale,
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing NL-to-SQL response', ['error' => $e->getMessage()]);
            
            return [
                'enabled' => false,
                'error' => 'Failed to parse response',
                'query' => $query,
            ];
        }
    }

    /**
     * Validate SQL before execution
     */
    public function validateSql(string $sql, array $params = []): array
    {
        $blacklist = ['DROP', 'TRUNCATE', 'DELETE FROM', 'ALTER', 'CREATE', 'GRANT', 'REVOKE'];

        foreach ($blacklist as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return [
                    'valid' => false,
                    'error' => "Dangerous SQL keyword detected: {$keyword}",
                ];
            }
        }

        // Verify parameter count matches
        $paramCount = substr_count($sql, '?');
        if ($paramCount !== count($params)) {
            return [
                'valid' => false,
                'error' => "Parameter mismatch: {$paramCount} placeholders, " . count($params) . " provided",
            ];
        }

        return ['valid' => true];
    }

    /**
     * Execute validated query
     */
    public function execute(string $sql, array $params = []): array
    {
        $validation = $this->validateSql($sql, $params);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
            ];
        }

        try {
            $results = DB::select($sql, $params);

            return [
                'success' => true,
                'rows' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            Log::error('NL-to-SQL execution error', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Query execution failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fallback when Claude API unavailable
     */
    private function getFallbackResponse(string $query): array
    {
        return [
            'enabled' => false,
            'error' => 'Claude API not available',
            'query' => $query,
            'suggestion' => 'Use the query builder UI or write SQL directly',
        ];
    }
}
