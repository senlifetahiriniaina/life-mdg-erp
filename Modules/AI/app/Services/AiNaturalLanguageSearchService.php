<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiNaturalLanguageSearchService
{
    private const CACHE_TTL = 120;
    private const SUPPORTED_MODULES = [
        'CRM'         => ['contacts', 'opportunities'],
        'Accounting'  => ['invoices', 'journal_entries'],
        'HR'          => ['employees', 'leave_requests'],
        'Inventory'   => ['products', 'stock_movements'],
        'Sales'       => ['orders', 'quotations'],
        'POS'         => ['pos_orders', 'pos_sessions'],
        'Achats'      => ['purchase_orders'],
        'Projects'    => ['projects', 'tasks'],
        'Ecommerce'   => ['ecommerce_orders', 'products'],
        'Contracts'   => ['contracts'],
        'Assets'      => ['assets'],
        'Reporting'   => ['reports'],
    ];

    private string $apiKey;
    private string $model;
    private bool $aiEnabled;

    public function __construct()
    {
        $this->apiKey    = config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        $this->model     = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->aiEnabled = $this->apiKey !== '';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Convert a natural language query to a structured search object.
     *
     * @param  string $query   e.g. "clients qui n'ont pas commandé depuis 3 mois"
     * @param  string $locale
     * @return array{module: string, entity: string, filters: array, sort: array, limit: int}
     */
    public function parseQuery(string $query, string $locale = 'fr'): array
    {
        if ($this->aiEnabled) {
            return $this->parseWithClaude($query, $locale);
        }

        return $this->parseWithKeywords($query, $locale);
    }

    /**
     * Execute a parsed query against the database.
     *
     * @param  array $parsedQuery
     * @param  int   $tenantId
     * @return array{results: array, count: int}
     */
    public function executeSearch(array $parsedQuery, int $tenantId): array
    {
        $module = $parsedQuery['module'] ?? 'CRM';
        $entity = $parsedQuery['entity'] ?? 'contacts';
        $limit  = min((int) ($parsedQuery['limit'] ?? 20), 100);

        // Validate entity belongs to module and tenant isolation is enforced
        $allowedEntities = self::SUPPORTED_MODULES[$module] ?? [];
        if (!in_array($entity, $allowedEntities, true)) {
            $entity = $allowedEntities[0] ?? 'contacts';
        }

        try {
            $query = DB::table($entity)->where('tenant_id', $tenantId);

            // Apply simple filters
            foreach (($parsedQuery['filters'] ?? []) as $filter) {
                if (!is_array($filter) || !isset($filter['field'])) {
                    continue;
                }

                $field    = (string) $filter['field'];
                $operator = (string) ($filter['operator'] ?? '=');
                $value    = $filter['value'] ?? null;

                if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>=', 'like'], true)) {
                    $operator = '=';
                }

                if ($operator === 'like') {
                    $query->where($field, 'like', '%' . $value . '%');
                } elseif ($value !== null) {
                    $query->where($field, $operator, $value);
                }
            }

            // Apply sort
            $sort = $parsedQuery['sort'] ?? [];
            if (!empty($sort['field'])) {
                $direction = strtolower((string) ($sort['direction'] ?? 'desc'));
                $query->orderBy((string) $sort['field'], in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc');
            }

            $total   = $query->count();
            $results = $query->limit($limit)->get()->toArray();

            return [
                'results' => array_map(fn ($row) => (array) $row, $results),
                'count'   => $total,
            ];
        } catch (\Throwable $e) {
            Log::warning('AiNaturalLanguageSearchService: executeSearch failed', [
                'entity' => $entity,
                'error'  => $e->getMessage(),
            ]);

            return ['results' => [], 'count' => 0];
        }
    }

    /**
     * Combined: parse the query then execute it.
     *
     * @return array{query: string, parsed: array, results: array, count: int, ai_powered: bool, suggestion: string|null}
     */
    public function search(string $query, int $tenantId, string $locale = 'fr'): array
    {
        $cacheKey = 'ai_nl_search:' . $tenantId . ':' . $locale . ':' . md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $tenantId, $locale): array {
            $parsed    = $this->parseQuery($query, $locale);
            $aiPowered = $this->aiEnabled;
            $results   = $this->executeSearch($parsed, $tenantId);

            return [
                'query'      => $query,
                'parsed'     => $parsed,
                'results'    => $results['results'],
                'count'      => $results['count'],
                'ai_powered' => $aiPowered,
                'suggestion' => $this->generateSuggestion($query, $results['count'], $locale),
            ];
        });
    }

    // -------------------------------------------------------------------------
    // Private — Claude parsing
    // -------------------------------------------------------------------------

    /** @return array{module: string, entity: string, filters: array, sort: array, limit: int} */
    private function parseWithClaude(string $query, string $locale): array
    {
        $modulesJson  = json_encode(self::SUPPORTED_MODULES);
        $systemPrompt = <<<PROMPT
You are WideHalo's natural language search parser.
Available modules and their tables: {$modulesJson}
Convert the user's query into a structured JSON search object with:
{
  "module": "ModuleName",
  "entity": "table_name",
  "filters": [{"field": "...", "operator": "=|!=|<|>|<=|>=|like", "value": ...}],
  "sort": {"field": "...", "direction": "asc|desc"},
  "limit": 20
}
Return only valid JSON. No markdown. Infer module from context. Default limit is 20.
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 400,
                'system'     => [
                    ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'messages' => [['role' => 'user', 'content' => $query]],
            ]);

            if (!$response->successful()) {
                return $this->parseWithKeywords($query, $locale);
            }

            $text    = $response->json('content.0.text', '{}');
            $text    = preg_replace('/^```json\s*/m', '', $text ?? '{}');
            $text    = preg_replace('/^```\s*/m', '', $text ?? '{}');
            $decoded = json_decode(trim($text), true);

            if (!is_array($decoded) || !isset($decoded['module'], $decoded['entity'])) {
                return $this->parseWithKeywords($query, $locale);
            }

            return $this->normaliseParsed($decoded);
        } catch (\Throwable $e) {
            Log::warning('AiNaturalLanguageSearchService: Claude parse failed', ['error' => $e->getMessage()]);
            return $this->parseWithKeywords($query, $locale);
        }
    }

    /** @return array{module: string, entity: string, filters: array, sort: array, limit: int} */
    private function parseWithKeywords(string $query, string $locale): array
    {
        $lower  = strtolower($query);
        $module = 'CRM';
        $entity = 'contacts';

        // Simple keyword matching for graceful degradation
        $moduleKeywords = [
            'Accounting'  => ['facture', 'invoice', 'comptab', 'bilan', 'payment'],
            'HR'          => ['employé', 'employee', 'paie', 'payroll', 'congé', 'leave'],
            'Inventory'   => ['produit', 'product', 'stock', 'inventaire'],
            'Sales'       => ['commande', 'order', 'vente', 'sale', 'devis', 'quotation'],
            'POS'         => ['caisse', 'pos', 'session'],
            'Achats'      => ['achat', 'purchase', 'fournisseur', 'supplier'],
            'Projects'    => ['projet', 'project', 'tâche', 'task'],
            'Ecommerce'   => ['boutique', 'shop', 'ecommerce', 'retour', 'return'],
            'Contracts'   => ['contrat', 'contract'],
            'Assets'      => ['actif', 'asset', 'amortissement', 'depreciation'],
            'Reporting'   => ['rapport', 'report', 'analyse', 'analysis'],
            'CRM'         => ['client', 'contact', 'prospect', 'opportunité', 'opportunity'],
        ];

        foreach ($moduleKeywords as $mod => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $module = $mod;
                    $entity = self::SUPPORTED_MODULES[$mod][0] ?? 'contacts';
                    break 2;
                }
            }
        }

        // Extract simple date filters (e.g. "depuis 3 mois" / "last 3 months")
        $filters = [];
        if (preg_match('/(\d+)\s*(mois|month)/i', $query, $m)) {
            $months    = (int) $m[1];
            $filters[] = [
                'field'    => 'created_at',
                'operator' => '<',
                'value'    => now()->subMonths($months)->toDateString(),
            ];
        }

        return [
            'module'  => $module,
            'entity'  => $entity,
            'filters' => $filters,
            'sort'    => ['field' => 'created_at', 'direction' => 'desc'],
            'limit'   => 20,
        ];
    }

    /** @return array{module: string, entity: string, filters: array, sort: array, limit: int} */
    private function normaliseParsed(array $decoded): array
    {
        return [
            'module'  => (string) ($decoded['module'] ?? 'CRM'),
            'entity'  => (string) ($decoded['entity'] ?? 'contacts'),
            'filters' => is_array($decoded['filters'] ?? null) ? $decoded['filters'] : [],
            'sort'    => is_array($decoded['sort'] ?? null) ? $decoded['sort'] : ['field' => 'created_at', 'direction' => 'desc'],
            'limit'   => (int) ($decoded['limit'] ?? 20),
        ];
    }

    private function generateSuggestion(string $query, int $count, string $locale): ?string
    {
        if ($count > 0) {
            return null;
        }

        return $locale === 'fr'
            ? 'Aucun résultat trouvé. Essayez des termes plus généraux.'
            : 'No results found. Try broader search terms.';
    }
}
