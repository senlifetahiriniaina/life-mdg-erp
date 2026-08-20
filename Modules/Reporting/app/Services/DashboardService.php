<?php

declare(strict_types=1);

namespace Modules\Reporting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Reporting\Models\Dashboard;
use Modules\Reporting\Models\ReportWidget;

/**
 * DashboardService
 *
 * Manages dashboards and widgets. Resolves live data for each widget
 * by delegating to the appropriate module service (Sales, Inventory, HR, etc.).
 * Generates AI narrative summaries of dashboard KPIs via Claude claude-sonnet-4-6.
 */
class DashboardService
{
    private const AI_MODEL   = 'claude-sonnet-4-6';
    private const CACHE_TTL  = 300;  // 5 minutes

    // ─── Widget data resolution ────────────────────────────────────────────────

    /**
     * Returns resolved data for a single widget.
     *
     * @return array{widget_id: int, widget_type: string, title: string, data: mixed, refreshed_at: string}
     */
    public function getWidgetData(ReportWidget $widget): array
    {
        $cacheKey = "widget_data:{$widget->id}:" . md5(json_encode($widget->data_source ?? []));

        $data = Cache::remember($cacheKey, $widget->refresh_interval_seconds ?? self::CACHE_TTL, function () use ($widget) {
            return $this->resolveDataSource($widget->data_source ?? [], $widget->tenant_id);
        });

        return [
            'widget_id'    => $widget->id,
            'widget_type'  => $widget->widget_type,
            'title'        => $widget->title,
            'data'         => $data,
            'config'       => $widget->config,
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Returns resolved data for all widgets on a dashboard.
     *
     * @return array<int, array>
     */
    public function getDashboardWidgetsData(Dashboard $dashboard): array
    {
        return $dashboard->widgets->map(fn (ReportWidget $w) => $this->getWidgetData($w))->toArray();
    }

    // ─── Dashboard management ─────────────────────────────────────────────────

    /**
     * Creates the default dashboard for a new tenant based on their industry.
     * textile → sales + inventory + production
     * commerce → sales + cashflow + top products
     * services → revenue + invoices + hr
     */
    public function createDefaultDashboard(int $tenantId, string $industry = 'commerce'): Dashboard
    {
        $dashboard = Dashboard::create([
            'tenant_id'   => $tenantId,
            'name'        => 'Tableau de bord principal',
            'description' => 'Dashboard par défaut — généré automatiquement',
            'is_default'  => true,
            'created_by'  => null,
            'layout'      => ['columns' => 4, 'rows' => 3],
            'shared_with' => ['admin', 'manager', 'director'],
        ]);

        $templates = $this->getDefaultWidgetTemplates($industry);
        foreach ($templates as $tpl) {
            ReportWidget::create(array_merge($tpl, [
                'tenant_id'    => $tenantId,
                'dashboard_id' => $dashboard->id,
            ]));
        }

        return $dashboard->load('widgets');
    }

    /**
     * Clones a named dashboard template for a tenant.
     * Available templates: 'executive', 'sales', 'finance', 'hr', 'inventory'
     */
    public function cloneTemplate(string $templateKey, int $tenantId): Dashboard
    {
        $templates = $this->getDashboardTemplates();

        if (! isset($templates[$templateKey])) {
            throw new \InvalidArgumentException("Template '{$templateKey}' non trouvé.");
        }

        $tpl = $templates[$templateKey];

        $dashboard = Dashboard::create([
            'tenant_id'   => $tenantId,
            'name'        => $tpl['name'],
            'description' => $tpl['description'],
            'is_default'  => false,
            'created_by'  => null,
            'layout'      => $tpl['layout'] ?? ['columns' => 4, 'rows' => 3],
            'shared_with' => $tpl['shared_with'] ?? [],
        ]);

        foreach ($tpl['widgets'] as $widgetDef) {
            ReportWidget::create(array_merge($widgetDef, [
                'tenant_id'    => $tenantId,
                'dashboard_id' => $dashboard->id,
            ]));
        }

        return $dashboard->load('widgets');
    }

    // ─── AI Summary ────────────────────────────────────────────────────────────

    /**
     * Generates a French AI narrative summary of the dashboard KPIs.
     * Example: "Ce mois-ci, votre CA est en hausse de 12%..."
     *
     * Falls back to a static template summary if the API is unavailable.
     */
    public function generateAiSummary(Dashboard $dashboard, string $locale = 'fr'): string
    {
        $cacheKey = "dashboard_summary:{$dashboard->id}:{$locale}:" . now()->format('Y-m-d-H');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($dashboard, $locale) {
            $widgetData = $this->getDashboardWidgetsData($dashboard);
            $summary    = $this->callClaudeForSummary($dashboard, $widgetData, $locale);

            return $summary ?? $this->buildStaticSummary($dashboard, $widgetData, $locale);
        });
    }

    // ─── Private: data resolution ─────────────────────────────────────────────

    /**
     * Resolves a widget's data_source config to actual data.
     * Dispatches to module-specific resolvers.
     *
     * Chantier 8 (Reporting): $tenantId is now the widget's own tenant_id
     * column, passed explicitly by getWidgetData() — every widget template
     * (getDefaultWidgetTemplates()/getDashboardTemplates()) sets data_source
     * to just {module, query}, never {..., params: {tenant_id}}, so the old
     * `$params['tenant_id'] ?? 1` fallback was unconditionally reached on
     * every real widget: every dashboard for every tenant was silently
     * showing tenant 1's Sales/Inventory/Accounting/HR/CRM data.
     *
     * @param array{module: string, query: string, params?: array} $dataSource
     */
    private function resolveDataSource(array $dataSource, int $tenantId): mixed
    {
        $module = $dataSource['module'] ?? 'Reporting';
        $query  = $dataSource['query']  ?? '';
        $params = $dataSource['params'] ?? [];

        try {
            return match ($module) {
                'Sales'       => $this->resolveSalesData($query, $params, $tenantId),
                'Inventory'   => $this->resolveInventoryData($query, $params, $tenantId),
                'Accounting'  => $this->resolveAccountingData($query, $params, $tenantId),
                'HR'          => $this->resolveHrData($query, $params, $tenantId),
                'CRM'         => $this->resolveCrmData($query, $params, $tenantId),
                default       => $this->resolveGenericData($query, $params, $tenantId),
            };
        } catch (\Exception $e) {
            Log::warning("DashboardService: data resolution failed for {$module}/{$query}", [
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Données temporairement indisponibles', 'module' => $module];
        }
    }

    /**
     * Chantier 19 (Lot 5): a deeper set of bugs than the "already correct"
     * baseline this method was assumed to be (the earlier resolvers'
     * table-name bug happened to overshadow this one, since fixing them
     * first is what surfaced this while writing the regression test).
     * `sales_orders`/`sales_order_lines` are the real tables, but none of
     * `order_date`/`total_amount`/`order_id`/`total_price` are real columns
     * on them (real: `confirmed_at`/`total`/`sales_order_id`/`line_total`)
     * — every one of the 3 queries here has been a guaranteed "no such
     * column" SQL error, silently swallowed the same way as every other
     * resolver in this file. `MONTH(order_date)` was additionally a
     * MySQL-only function that would have fatally errored under this app's
     * real sqlite dev/test/CI driver even with the column name fixed —
     * grouped in PHP instead, the same portable pattern already established
     * by Achats' PurchaseReportsController::spending() (Chantier 19 Lot 3).
     * `top_products` joins the real product catalogue, `inventory_products`
     * (Sales has no product model of its own; `sales_order_lines.product_id`
     * is a bare FK with no declared relation, and every other module that
     * references a Sales-order product already joins `inventory_products`).
     */
    private function resolveSalesData(string $query, array $params, int $tenantId): mixed
    {
        return match ($query) {
            'monthly_revenue' => DB::table('sales_orders')
                ->where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->whereYear('confirmed_at', now()->year)
                ->get(['confirmed_at', 'total'])
                ->groupBy(fn ($row) => (int) \Illuminate\Support\Carbon::parse($row->confirmed_at)->format('n'))
                ->map(fn ($rows, $month) => ['month' => $month, 'revenue' => (float) $rows->sum('total')])
                ->sortKeys()
                ->values()
                ->toArray(),

            'top_products' => DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->join('inventory_products as p', 'p.id', '=', 'sol.product_id')
                ->where('so.tenant_id', $tenantId)
                ->whereDate('so.confirmed_at', '>=', now()->startOfMonth())
                ->selectRaw('p.name, SUM(sol.quantity) as qty, SUM(sol.line_total) as revenue')
                ->groupBy('p.id', 'p.name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->toArray(),

            'kpi_revenue_month' => [
                'value'  => DB::table('sales_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'confirmed')
                    ->whereDate('confirmed_at', '>=', now()->startOfMonth())
                    ->sum('total'),
                'label'  => 'CA ce mois',
                'format' => 'currency',
            ],

            default => [],
        };
    }

    /**
     * Chantier 19 (Lot 5): every one of this resolver's 3 queries targeted a
     * bare `products` table with `stock_qty`/`reorder_point`/`cost_price`
     * columns — but the real `products` table (from the catch-all scaffold
     * migration, kept only for CostEngineService/ProductionForecastService)
     * has just `id/tenant_id/name/sku/status`, none of those 3 columns, and
     * the module's real product catalogue is a completely different table,
     * `inventory_products` (+ per-warehouse `inventory_stock`). Confirmed
     * empirically: every real widget call threw "no such column", silently
     * swallowed by resolveDataSource()'s own try/catch — every Inventory
     * dashboard widget has shown "Données temporairement indisponibles"
     * instead of real data since this file was written. Repointed at the
     * real tables/columns. `inventory_products.tenant_id` is itself a
     * documented phantom column elsewhere in this app (populated from the
     * equally-phantom `users.tenant_id`, confirmed in Chantier 19 Lot 3's
     * AiAnomalyDetectionService fix) — not re-litigated here, since fixing
     * Inventory's own tenant population is out of this Reporting-module
     * fix's scope; the filter is kept only so behavior doesn't regress
     * against whatever the real write path already does.
     */
    private function resolveInventoryData(string $query, array $params, int $tenantId): mixed
    {
        return match ($query) {
            'low_stock' => DB::table('inventory_products as p')
                ->leftJoin('inventory_stock as s', 's.product_id', '=', 'p.id')
                ->where('p.tenant_id', $tenantId)
                ->where('p.reorder_point', '>', 0)
                ->groupBy('p.id', 'p.name', 'p.sku', 'p.reorder_point')
                ->havingRaw('COALESCE(SUM(s.quantity), 0) <= p.reorder_point')
                ->selectRaw('p.id, p.name, p.sku, COALESCE(SUM(s.quantity), 0) as stock_qty, p.reorder_point')
                ->orderBy('stock_qty')
                ->limit(20)
                ->get()
                ->toArray(),

            'kpi_stock_value' => [
                'value'  => DB::table('inventory_stock as s')
                    ->join('inventory_products as p', 'p.id', '=', 's.product_id')
                    ->where('p.tenant_id', $tenantId)
                    ->selectRaw('SUM(s.quantity * p.cost_price)')
                    ->value(DB::raw('SUM(s.quantity * p.cost_price)')) ?? 0,
                'label'  => 'Valeur du stock',
                'format' => 'currency',
            ],

            'kpi_stockout_count' => [
                'value'  => DB::table('inventory_products as p')
                    ->leftJoin('inventory_stock as s', 's.product_id', '=', 'p.id')
                    ->where('p.tenant_id', $tenantId)
                    ->groupBy('p.id')
                    ->havingRaw('COALESCE(SUM(s.quantity), 0) <= 0')
                    ->get()
                    ->count(),
                'label'  => 'Produits en rupture',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    /**
     * Chantier 19 (Lot 5): `invoices`/`supplier_invoices` never existed
     * anywhere in this repo (confirmed via a repo-wide grep for either
     * `Schema::create`) — a guaranteed "table not found" error on every real
     * call, silently swallowed the same way as the Inventory resolver above.
     * Receivables is repointed to the real `acc_invoices` table (the same
     * one Chantier 18's OHADA financial statements read), reusing
     * `Invoice::scopeUnpaid()`'s own `status NOT IN (paid, cancelled)` rule
     * inline since this method uses the query builder, not Eloquent.
     * `acc_invoices` has no tenant/company column at all — this app posts
     * to one shared ledger by design (documented in Chantier 18's
     * OhadaReportService fix), so $tenantId is accepted for signature
     * parity with the other resolvers but not filtered on here, matching
     * that same precedent. Payables has no real "supplier invoice with a
     * payment status" concept anywhere in this app (Achats only tracks
     * purchase orders, never invoices/payments) — approximated via
     * not-yet-received purchase order totals rather than left querying a
     * table that has never existed; `achats_purchase_orders.company_id` is
     * real and populated (Chantier 19 Lot 3's Achats tenant-isolation fix).
     */
    private function resolveAccountingData(string $query, array $params, int $tenantId): mixed
    {
        return match ($query) {
            'kpi_outstanding_receivables' => [
                'value'  => DB::table('acc_invoices')
                    ->whereNotIn('status', ['paid', 'cancelled'])
                    ->selectRaw('SUM(total - COALESCE(amount_paid, 0))')
                    ->value(DB::raw('SUM(total - COALESCE(amount_paid, 0))')) ?? 0,
                'label'  => 'Créances en cours',
                'format' => 'currency',
            ],

            'kpi_outstanding_payables' => [
                'value'  => DB::table('achats_purchase_orders')
                    ->where('company_id', $tenantId)
                    ->whereNotIn('status', ['received', 'cancelled', 'rejected'])
                    ->selectRaw('SUM(total_amount)')
                    ->value(DB::raw('SUM(total_amount)')) ?? 0,
                'label'  => 'Dettes fournisseurs (commandes non réceptionnées)',
                'format' => 'currency',
            ],

            default => [],
        };
    }

    /**
     * Chantier 19 (Lot 5): `employees`/`leave_requests` never existed
     * anywhere in this repo — same guaranteed-error, silently-swallowed bug
     * class as the two resolvers above. Repointed to the real
     * `hr_employees`/`hr_leave_requests` tables. `hr_leave_requests` has no
     * tenant/company column at all, and `hr_employees.tenant_id` is itself
     * a documented, never-populated phantom column (CLAUDE.md's own
     * Chantier 8.3hp entry: "a third, phantom column, referenced nowhere in
     * live HR code") — HR-wide tenant isolation is a confirmed, explicitly
     * out-of-scope gap (Chantier 19 Lot 2: "a module-wide retrofit... left
     * undone"), not something a Reporting-module widget fix should silently
     * paper over by inventing scoping HR itself doesn't have; left
     * unfiltered by tenant here, matching HR's own current (documented)
     * behavior everywhere else in the app.
     */
    private function resolveHrData(string $query, array $params, int $tenantId): mixed
    {
        return match ($query) {
            'kpi_headcount' => [
                'value'  => DB::table('hr_employees')
                    ->where('status', 'active')
                    ->count(),
                'label'  => 'Effectif actif',
                'format' => 'integer',
            ],

            'kpi_pending_leaves' => [
                'value'  => DB::table('hr_leave_requests')
                    ->where('status', 'pending')
                    ->count(),
                'label'  => 'Congés en attente',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    /**
     * Chantier 19 (Lot 5): `leads` never existed anywhere in this repo —
     * same bug class as the resolvers above. Repointed to the real
     * `crm_leads` table, filtered by its real `company_id` column (the
     * tenant-boundary column CRM's LeadController::store() actually
     * populates as of Chantier 19 Lot 1's CRM re-audit — `tenant_id` on
     * this same table is the equally-real-but-legacy column CRM's own
     * services still write in parallel; company_id is the one every
     * already-fixed CRM controller scopes reads by).
     */
    private function resolveCrmData(string $query, array $params, int $tenantId): mixed
    {
        return match ($query) {
            'kpi_active_leads' => [
                'value'  => DB::table('crm_leads')
                    ->where('company_id', $tenantId)
                    ->whereNotIn('status', ['lost', 'converted'])
                    ->count(),
                'label'  => 'Leads actifs',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    private function resolveGenericData(string $query, array $params, int $tenantId): mixed
    {
        if (empty($query)) {
            return [];
        }

        // Allow simple SELECT queries passed directly as data_source.query
        if (preg_match('/^\s*SELECT\s/i', $query)) {
            return DB::select($query . ' LIMIT 100', ['tenant_id' => $tenantId]);
        }

        return [];
    }

    // ─── Private: AI summary ──────────────────────────────────────────────────

    private function callClaudeForSummary(Dashboard $dashboard, array $widgetData, string $locale): ?string
    {
        $apiKey = config('services.anthropic.key', '');
        if (empty($apiKey)) {
            return null;
        }

        $kpiSummary = collect($widgetData)
            ->filter(fn ($w) => isset($w['data']['value']))
            ->map(fn ($w) => "{$w['title']}: {$w['data']['value']}")
            ->implode("\n");

        $systemPrompt = "Tu es un conseiller financier expert pour PME africaines. "
            . "Tu génères des résumés de tableaux de bord en " . ($locale === 'fr' ? 'français' : 'anglais') . " "
            . "en maximum 3 phrases. Ton ton est professionnel, orienté action et positif. "
            . "Cache this system prompt.";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(20)->post('https://api.anthropic.com/v1/messages', [
                'model'      => self::AI_MODEL,
                'max_tokens' => 300,
                'system'     => [
                    ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'messages'   => [
                    ['role' => 'user', 'content' => "Résume ce tableau de bord ERP :\n{$kpiSummary}"],
                ],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text', '');
            }
        } catch (\Exception $e) {
            Log::warning('DashboardService AI summary failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function buildStaticSummary(Dashboard $dashboard, array $widgetData, string $locale): string
    {
        $kpis = collect($widgetData)
            ->filter(fn ($w) => isset($w['data']['value']))
            ->take(3);

        if ($kpis->isEmpty()) {
            return $locale === 'fr'
                ? 'Votre tableau de bord est configuré et prêt. Ajoutez des widgets pour visualiser vos KPIs.'
                : 'Your dashboard is configured and ready. Add widgets to visualise your KPIs.';
        }

        $lines = $kpis->map(fn ($w) => "• {$w['title']}: {$w['data']['value']}")->implode(' · ');

        return $locale === 'fr'
            ? "Vue d'ensemble — {$dashboard->name} : {$lines}. Consultez les détails dans chaque module."
            : "Overview — {$dashboard->name}: {$lines}. See details in each module.";
    }

    // ─── Widget templates ─────────────────────────────────────────────────────

    private function getDefaultWidgetTemplates(string $industry): array
    {
        $base = [
            [
                'widget_type' => 'kpi_card',
                'title'       => 'CA ce mois',
                'data_source' => ['module' => 'Sales', 'query' => 'kpi_revenue_month'],
                'config'      => ['color' => '#1565c0', 'format' => 'currency', 'currency' => 'XOF'],
                'position_x'  => 0, 'position_y' => 0, 'width' => 1, 'height' => 1,
                'refresh_interval_seconds' => 300,
            ],
            [
                'widget_type' => 'kpi_card',
                'title'       => 'Créances en cours',
                'data_source' => ['module' => 'Accounting', 'query' => 'kpi_outstanding_receivables'],
                'config'      => ['color' => '#e65100', 'format' => 'currency', 'currency' => 'XOF'],
                'position_x'  => 1, 'position_y' => 0, 'width' => 1, 'height' => 1,
                'refresh_interval_seconds' => 600,
            ],
            [
                'widget_type' => 'kpi_card',
                'title'       => 'Effectif actif',
                'data_source' => ['module' => 'HR', 'query' => 'kpi_headcount'],
                'config'      => ['color' => '#2e7d32', 'format' => 'integer'],
                'position_x'  => 2, 'position_y' => 0, 'width' => 1, 'height' => 1,
                'refresh_interval_seconds' => 3600,
            ],
            [
                'widget_type' => 'bar_chart',
                'title'       => 'CA mensuel',
                'data_source' => ['module' => 'Sales', 'query' => 'monthly_revenue'],
                'config'      => ['color' => '#1565c0', 'x_key' => 'month', 'y_key' => 'revenue'],
                'position_x'  => 0, 'position_y' => 1, 'width' => 2, 'height' => 2,
                'refresh_interval_seconds' => 600,
            ],
        ];

        $industryExtra = match ($industry) {
            'textile', 'manufacturing' => [
                [
                    'widget_type' => 'kpi_card',
                    'title'       => 'Valeur du stock',
                    'data_source' => ['module' => 'Inventory', 'query' => 'kpi_stock_value'],
                    'config'      => ['color' => '#6a1b9a', 'format' => 'currency', 'currency' => 'XOF'],
                    'position_x'  => 3, 'position_y' => 0, 'width' => 1, 'height' => 1,
                    'refresh_interval_seconds' => 600,
                ],
                [
                    'widget_type' => 'table',
                    'title'       => 'Produits en rupture',
                    'data_source' => ['module' => 'Inventory', 'query' => 'low_stock'],
                    'config'      => ['columns' => ['name', 'sku', 'stock_qty', 'reorder_point']],
                    'position_x'  => 2, 'position_y' => 1, 'width' => 2, 'height' => 2,
                    'refresh_interval_seconds' => 300,
                ],
            ],
            'commerce', 'retail' => [
                [
                    'widget_type' => 'kpi_card',
                    'title'       => 'Produits en rupture',
                    'data_source' => ['module' => 'Inventory', 'query' => 'kpi_stockout_count'],
                    'config'      => ['color' => '#c62828', 'format' => 'integer', 'threshold_warning' => 5],
                    'position_x'  => 3, 'position_y' => 0, 'width' => 1, 'height' => 1,
                    'refresh_interval_seconds' => 300,
                ],
                [
                    'widget_type' => 'pie_chart',
                    'title'       => 'Top 10 produits',
                    'data_source' => ['module' => 'Sales', 'query' => 'top_products'],
                    'config'      => ['value_key' => 'revenue', 'label_key' => 'name'],
                    'position_x'  => 2, 'position_y' => 1, 'width' => 2, 'height' => 2,
                    'refresh_interval_seconds' => 600,
                ],
            ],
            default => [],
        };

        return array_merge($base, $industryExtra);
    }

    private function getDashboardTemplates(): array
    {
        return [
            'executive' => [
                'name'        => 'Vue dirigeant',
                'description' => 'KPIs stratégiques pour direction générale',
                'layout'      => ['columns' => 4, 'rows' => 3],
                'shared_with' => ['director', 'ceo', 'cfo'],
                'widgets'     => $this->getDefaultWidgetTemplates('commerce'),
            ],
            'sales' => [
                'name'        => 'Performance commerciale',
                'description' => 'Pipeline, CA, top produits',
                'layout'      => ['columns' => 4, 'rows' => 3],
                'shared_with' => ['sales_manager', 'sales_rep'],
                'widgets'     => $this->getDefaultWidgetTemplates('commerce'),
            ],
            'finance' => [
                'name'        => 'Finance & Trésorerie',
                'description' => 'Créances, dettes, trésorerie, TVA',
                'layout'      => ['columns' => 4, 'rows' => 3],
                'shared_with' => ['cfo', 'accountant'],
                'widgets'     => [
                    [
                        'widget_type' => 'kpi_card', 'title' => 'Créances en cours',
                        'data_source' => ['module' => 'Accounting', 'query' => 'kpi_outstanding_receivables'],
                        'config' => ['color' => '#e65100', 'format' => 'currency'], 'position_x' => 0, 'position_y' => 0, 'width' => 1, 'height' => 1, 'refresh_interval_seconds' => 600,
                    ],
                    [
                        'widget_type' => 'kpi_card', 'title' => 'Dettes fournisseurs',
                        'data_source' => ['module' => 'Accounting', 'query' => 'kpi_outstanding_payables'],
                        'config' => ['color' => '#1565c0', 'format' => 'currency'], 'position_x' => 1, 'position_y' => 0, 'width' => 1, 'height' => 1, 'refresh_interval_seconds' => 600,
                    ],
                ],
            ],
            'hr' => [
                'name'        => 'Ressources Humaines',
                'description' => 'Effectif, congés, paie',
                'layout'      => ['columns' => 4, 'rows' => 3],
                'shared_with' => ['hr_manager', 'hr_officer'],
                'widgets'     => [
                    [
                        'widget_type' => 'kpi_card', 'title' => 'Effectif actif',
                        'data_source' => ['module' => 'HR', 'query' => 'kpi_headcount'],
                        'config' => ['color' => '#2e7d32', 'format' => 'integer'], 'position_x' => 0, 'position_y' => 0, 'width' => 1, 'height' => 1, 'refresh_interval_seconds' => 3600,
                    ],
                    [
                        'widget_type' => 'kpi_card', 'title' => 'Congés en attente',
                        'data_source' => ['module' => 'HR', 'query' => 'kpi_pending_leaves'],
                        'config' => ['color' => '#f9a825', 'format' => 'integer'], 'position_x' => 1, 'position_y' => 0, 'width' => 1, 'height' => 1, 'refresh_interval_seconds' => 600,
                    ],
                ],
            ],
            'inventory' => [
                'name'        => 'Gestion des stocks',
                'description' => 'Valeur stock, ruptures, mouvements',
                'layout'      => ['columns' => 4, 'rows' => 3],
                'shared_with' => ['warehouse_manager', 'stock_officer'],
                'widgets'     => $this->getDefaultWidgetTemplates('textile'),
            ],
        ];
    }
}
