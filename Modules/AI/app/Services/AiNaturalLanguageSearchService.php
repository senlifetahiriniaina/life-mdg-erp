<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiNaturalLanguageSearchService
{
    private const CACHE_TTL = 120;

    /**
     * Real `<module>_`-prefixed table names (confirmed against each module's own
     * migrations), not the bare/generic table names this map used to carry —
     * `DB::table($entity)` on the old names always 404'd into `executeSearch()`'s
     * own catch block, silently returning empty results on every real search.
     * POS/Ecommerce/Contracts/Assets are also dropped entirely: those modules are
     * not part of Life MDG's 27-module scope (see CLAUDE.md) and none of their
     * tables (`pos_orders`, `ecommerce_orders`, `contracts`, `assets`) exist
     * anywhere in this repo's migrations.
     */
    private const SUPPORTED_MODULES = [
        'CRM'         => ['crm_contacts', 'crm_opportunities'],
        'Accounting'  => ['acc_invoices', 'acc_journal_entries'],
        'HR'          => ['hr_employees', 'hr_leave_requests'],
        'Inventory'   => ['inventory_products', 'inventory_stock_movements'],
        'Sales'       => ['sales_orders', 'sales_quotations'],
        'Achats'      => ['achats_purchase_orders'],
        'Projects'    => ['prj_projects', 'prj_tasks'],
        'Reporting'   => ['report_definitions'],
    ];

    /**
     * Tenant scoping is genuinely inconsistent across these tables in this app
     * (a documented, recurring issue — see CLAUDE.md's Chantier 8.x notes):
     * some have a real, populated `tenant_id` (`inventory_products`,
     * `sales_orders`, `sales_quotations`, `achats_purchase_orders`,
     * `report_definitions`, `crm_contacts`), and several have none at all
     * (`crm_opportunities`, `acc_invoices`, `acc_journal_entries`,
     * `hr_employees`, `hr_leave_requests`, `inventory_stock_movements`,
     * `prj_projects`, `prj_tasks`). Deliberately NOT falling back to
     * `company_id` here even where a column of that name exists — on
     * `crm_contacts` in particular, `company_id` is a foreign key to
     * `Modules\CRM\Models\Company` (a CRM "customer's company" record), not
     * this app's tenant-boundary `company_id` (see `App\Http\Middleware\
     * InitializeTenancyFromAuthenticatedUser`) — a same-name column meaning a
     * completely different thing, and filtering by it would silently return
     * wrong-tenant data rather than the intended tenant's. Rather than guess
     * per-table semantics this module has no authority over, the filter is
     * applied only when the table has the real `tenant_id` column (which
     * `executeSearch()`'s catch block would otherwise silently swallow into an
     * empty result on every search — the exact failure mode this fix closes);
     * tables with no `tenant_id` at all return company-wide results, a known,
     * pre-existing gap in those other modules' own schemas that this AI-module
     * fix cannot close on its own.
     */
    private function resolveTenantColumn(string $table): ?string
    {
        return Schema::hasColumn($table, 'tenant_id') ? 'tenant_id' : null;
    }

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
            $query = DB::table($entity);

            $tenantColumn = $this->resolveTenantColumn($entity);
            if ($tenantColumn !== null) {
                $query->where($tenantColumn, $tenantId);
            }

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
