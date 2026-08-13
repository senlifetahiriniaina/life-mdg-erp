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
            return $this->resolveDataSource($widget->data_source ?? []);
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
            'owner_id'    => null,
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
            'owner_id'    => null,
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
     * @param array{module: string, query: string, params?: array} $dataSource
     */
    private function resolveDataSource(array $dataSource): mixed
    {
        $module = $dataSource['module'] ?? 'Reporting';
        $query  = $dataSource['query']  ?? '';
        $params = $dataSource['params'] ?? [];

        try {
            return match ($module) {
                'Sales'       => $this->resolveSalesData($query, $params),
                'Inventory'   => $this->resolveInventoryData($query, $params),
                'Accounting'  => $this->resolveAccountingData($query, $params),
                'HR'          => $this->resolveHrData($query, $params),
                'CRM'         => $this->resolveCrmData($query, $params),
                default       => $this->resolveGenericData($query, $params),
            };
        } catch (\Exception $e) {
            Log::warning("DashboardService: data resolution failed for {$module}/{$query}", [
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Données temporairement indisponibles', 'module' => $module];
        }
    }

    private function resolveSalesData(string $query, array $params): mixed
    {
        return match ($query) {
            'monthly_revenue' => DB::table('sales_orders')
                ->where('tenant_id', $params['tenant_id'] ?? 1)
                ->where('status', 'confirmed')
                ->whereYear('order_date', now()->year)
                ->selectRaw('MONTH(order_date) as month, SUM(total_amount) as revenue')
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->toArray(),

            'top_products' => DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.order_id')
                ->join('products as p', 'p.id', '=', 'sol.product_id')
                ->where('so.tenant_id', $params['tenant_id'] ?? 1)
                ->whereDate('so.order_date', '>=', now()->startOfMonth())
                ->selectRaw('p.name, SUM(sol.quantity) as qty, SUM(sol.total_price) as revenue')
                ->groupBy('p.id', 'p.name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->toArray(),

            'kpi_revenue_month' => [
                'value'  => DB::table('sales_orders')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->whereDate('order_date', '>=', now()->startOfMonth())
                    ->sum('total_amount'),
                'label'  => 'CA ce mois',
                'format' => 'currency',
            ],

            default => [],
        };
    }

    private function resolveInventoryData(string $query, array $params): mixed
    {
        return match ($query) {
            'low_stock' => DB::table('products')
                ->where('tenant_id', $params['tenant_id'] ?? 1)
                ->whereColumn('stock_qty', '<=', 'reorder_point')
                ->where('reorder_point', '>', 0)
                ->select('id', 'name', 'sku', 'stock_qty', 'reorder_point')
                ->orderBy('stock_qty')
                ->limit(20)
                ->get()
                ->toArray(),

            'kpi_stock_value' => [
                'value'  => DB::table('products')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->selectRaw('SUM(stock_qty * cost_price)')
                    ->value(DB::raw('SUM(stock_qty * cost_price)')),
                'label'  => 'Valeur du stock',
                'format' => 'currency',
            ],

            'kpi_stockout_count' => [
                'value'  => DB::table('products')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->where('stock_qty', '<=', 0)
                    ->count(),
                'label'  => 'Produits en rupture',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    private function resolveAccountingData(string $query, array $params): mixed
    {
        return match ($query) {
            'kpi_outstanding_receivables' => [
                'value'  => DB::table('invoices')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->whereIn('status', ['sent', 'partial', 'overdue'])
                    ->selectRaw('SUM(total_amount - COALESCE(paid_amount, 0))')
                    ->value(DB::raw('SUM(total_amount - COALESCE(paid_amount, 0))')),
                'label'  => 'Créances en cours',
                'format' => 'currency',
            ],

            'kpi_outstanding_payables' => [
                'value'  => DB::table('supplier_invoices')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->whereIn('status', ['received', 'partial', 'overdue'])
                    ->selectRaw('SUM(total_amount - COALESCE(paid_amount, 0))')
                    ->value(DB::raw('SUM(total_amount - COALESCE(paid_amount, 0))')),
                'label'  => 'Dettes fournisseurs',
                'format' => 'currency',
            ],

            default => [],
        };
    }

    private function resolveHrData(string $query, array $params): mixed
    {
        return match ($query) {
            'kpi_headcount' => [
                'value'  => DB::table('employees')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->where('status', 'active')
                    ->count(),
                'label'  => 'Effectif actif',
                'format' => 'integer',
            ],

            'kpi_pending_leaves' => [
                'value'  => DB::table('leave_requests')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->where('status', 'pending')
                    ->count(),
                'label'  => 'Congés en attente',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    private function resolveCrmData(string $query, array $params): mixed
    {
        return match ($query) {
            'kpi_active_leads' => [
                'value'  => DB::table('leads')
                    ->where('tenant_id', $params['tenant_id'] ?? 1)
                    ->whereNotIn('status', ['lost', 'converted'])
                    ->count(),
                'label'  => 'Leads actifs',
                'format' => 'integer',
            ],

            default => [],
        };
    }

    private function resolveGenericData(string $query, array $params): mixed
    {
        if (empty($query)) {
            return [];
        }

        // Allow simple SELECT queries passed directly as data_source.query
        if (preg_match('/^\s*SELECT\s/i', $query)) {
            $tenantId = $params['tenant_id'] ?? 1;
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
