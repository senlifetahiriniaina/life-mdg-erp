<?php

declare(strict_types=1);

namespace Modules\Strategy\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Central registry mapping each ERP module to its KPIs with pull callbacks.
 * Each KPI definition carries: key, label, unit, direction, formula description,
 * and a callable that returns the current float value from real DB tables.
 *
 * All queries are wrapped in try/catch so the registry degrades gracefully
 * when a module's tables don't yet exist (e.g. fresh install, partial seed).
 *
 * Chantier 32.27 (audit 14 couches — layer 6/8, deep security/business
 * validation): every value_callback here previously ran a fully
 * tenant-unfiltered aggregate across every company's data — StrategyRatioService
 * ::enrichRatio() received a real $tenantId from every caller (all 9 API
 * controllers' tenantId() helpers were already correctly fixed to company_id
 * across Chantiers 8.5ars/8.6/10/19), but never actually forwarded it into
 * this registry, so every ratio (revenue, CAC, turnover, CSAT, …) shown on
 * every company's cockpit was silently computed over the WHOLE app's data
 * combined — a live cross-tenant data-blending leak, confirmed empirically
 * (2 companies, distinct invoices/opportunities, one shared aggregate value
 * on both). Fixed by threading an optional $tenantId through
 * all()/forModule()/getValue() and every module's *KPIs() builder, filtering
 * each query by the real tenant-boundary column the underlying table
 * actually has:
 *   - crm_leads/crm_opportunities/sales_orders/mfg_production_orders/
 *     hd_chat_sessions/hr_job_postings/inventory_products: real `tenant_id`,
 *     already populated from company_id by their own module's write path
 *     (confirmed via each module's own Chantier 8.x/10/19/26 fix — e.g.
 *     Sales' SalesObjectiveService already filters sales_orders.tenant_id the
 *     same way).
 *   - hr_employees/hd_tickets: real `company_id` (HR's Chantier 32.17 audit,
 *     Helpdesk's Chantier 32.17 audit).
 *   - crm_leads ALSO has a same-named `company_id` column — a confirmed
 *     landmine documented elsewhere in this session (AiNaturalLanguageSearchService):
 *     it is the lead's own organisation name FK, NOT this app's tenant
 *     boundary. Deliberately never used here — `tenant_id` only.
 *   - inventory_stock/inventory_stock_movements have no tenant column of
 *     their own but both carry product_id, joined to inventory_products.tenant_id.
 *   - hr_leave_requests has no tenant column of its own; joined via
 *     employee_id to hr_employees.company_id.
 *   - acc_invoices/acc_journal_entries have NO tenant/company column
 *     anywhere in this repo (a pre-existing, already-documented cross-module
 *     gap — see CLAUDE.md's Chantier 32.13-32.16 Achats note: "confirmed
 *     that Modules\Accounting\Models\Invoice/acc_invoices has strictly no
 *     tenant/company column, impossible to fix from a sibling module alone").
 *     Left unscoped here too — a real, still-open gap this audit did not
 *     invent and cannot close from Strategy alone; every Accounting-sourced
 *     ratio (current_ratio, net_profit_margin, dso, revenue_per_employee)
 *     therefore still mixes every company's invoices until Accounting itself
 *     gets a real tenant column, documented rather than silently guessed at.
 *
 * $tenantId defaults to null (no filter) so the pre-existing unit tests
 * (KPIRegistryServiceTest — which call all()/forModule()/getValue() with no
 * tenant argument) keep their original, deliberately-unscoped behaviour.
 */
class KPIRegistryService
{
    /**
     * @return array<string, array<string, array{
     *   label: string,
     *   unit: string,
     *   direction: 'up'|'down'|'target',
     *   formula: string,
     *   value_callback: callable
     * }>>
     */
    public function all(?string $tenantId = null): array
    {
        return [
            'Accounting'    => $this->accountingKPIs($tenantId),
            'CRM'           => $this->crmKPIs($tenantId),
            'HR'            => $this->hrKPIs($tenantId),
            'Inventory'     => $this->inventoryKPIs($tenantId),
            'Sales'         => $this->salesKPIs($tenantId),
            'Manufacturing' => $this->manufacturingKPIs($tenantId),
            'Helpdesk'      => $this->helpdeskKPIs($tenantId),
        ];
    }

    /**
     * Get KPIs for a specific module.
     *
     * @return array<string, array{label: string, unit: string, direction: string, formula: string, value_callback: callable}>
     */
    public function forModule(string $module, ?string $tenantId = null): array
    {
        return $this->all($tenantId)[$module] ?? [];
    }

    /**
     * Pull the current value for a single KPI.
     */
    public function getValue(string $module, string $key, ?string $tenantId = null): float
    {
        $kpis = $this->forModule($module, $tenantId);
        if (!isset($kpis[$key])) {
            return 0.0;
        }
        return (float) ($kpis[$key]['value_callback'])();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Execute a DB query and return a float, falling back to $default on any error. */
    private function query(callable $fn, float $default): float
    {
        try {
            $result = $fn();
            return $result === null ? $default : (float) $result;
        } catch (\Throwable) {
            return $default;
        }
    }

    // ─── Module KPI definitions ───────────────────────────────────────────────

    private function accountingKPIs(?string $tenantId): array
    {
        return [
            'current_ratio' => [
                'label'          => 'Current Ratio',
                'unit'           => 'x',
                'direction'      => 'up',
                'formula'        => 'actifs_courants / passifs_courants',
                // acc_invoices has no tenant/company column anywhere in this
                // repo (documented cross-module gap, see class docblock) —
                // deliberately left unscoped rather than silently guessed.
                'value_callback' => fn() => $this->query(function () {
                    // Current assets proxy: cash + outstanding receivables (paid < total)
                    $receivables = DB::table('acc_invoices')
                        ->where('status', 'sent')
                        ->whereColumn('amount_paid', '<', 'total')
                        ->sum(DB::raw('total - amount_paid'));
                    // Current liabilities proxy: overdue payables (amount_due > 0)
                    $payables = DB::table('acc_invoices')
                        ->where('status', 'overdue')
                        ->sum('amount_due');
                    return $payables > 0 ? round($receivables / $payables, 2) : 1.82;
                }, 1.82),
            ],

            'debt_to_equity' => [
                'label'          => 'Debt-to-Equity',
                'unit'           => 'x',
                'direction'      => 'down',
                'formula'        => 'total_dettes / total_fonds_propres',
                'value_callback' => fn() => 0.65, // requires balance sheet model — fallback until ChartOfAccount aggregation is wired
            ],

            'net_profit_margin' => [
                'label'          => 'Net Profit Margin',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'bénéfice_net / CA × 100',
                // acc_invoices/acc_journal_entries: no tenant column, see docblock.
                'value_callback' => fn() => $this->query(function () {
                    $now      = Carbon::now();
                    $revenue  = (float) DB::table('acc_invoices')
                        ->whereIn('status', ['paid', 'partial'])
                        ->whereYear('created_at', $now->year)
                        ->whereMonth('created_at', $now->month)
                        ->sum('amount_paid');
                    $expenses = (float) DB::table('acc_journal_entries')
                        ->where('type', 'expense')
                        ->whereYear('created_at', $now->year)
                        ->whereMonth('created_at', $now->month)
                        ->sum('debit');
                    return $revenue > 0 ? round(($revenue - $expenses) / $revenue * 100, 1) : 14.3;
                }, 14.3),
            ],

            'ebitda_margin' => [
                'label'          => 'EBITDA Margin',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'EBITDA / CA × 100',
                'value_callback' => fn() => 22.8, // requires P&L aggregation — fallback
            ],

            'dso' => [
                'label'          => 'DSO (Délai moyen de recouvrement)',
                'unit'           => 'jours',
                'direction'      => 'down',
                'formula'        => 'créances_clients / (CA / 365)',
                // acc_invoices: no tenant column, see docblock.
                'value_callback' => fn() => $this->query(function () {
                    $receivables = (float) DB::table('acc_invoices')
                        ->whereIn('status', ['sent', 'overdue'])
                        ->sum('amount_due');
                    $annualRevenue = (float) DB::table('acc_invoices')
                        ->whereIn('status', ['paid', 'partial'])
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('amount_paid');
                    return $annualRevenue > 0 ? round($receivables / ($annualRevenue / 365), 1) : 38.5;
                }, 38.5),
            ],

            'dpo' => [
                'label'          => 'DPO (Délai moyen de paiement fournisseurs)',
                'unit'           => 'jours',
                'direction'      => 'up',
                'formula'        => 'dettes_fournisseurs / (COGS / 365)',
                'value_callback' => fn() => 45.2, // requires supplier invoice table — fallback
            ],
        ];
    }

    private function crmKPIs(?string $tenantId): array
    {
        return [
            'lead_conversion_rate' => [
                'label'          => 'Taux de conversion Leads',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'leads_convertis / total_leads × 100',
                // crm_leads.tenant_id — deliberately NOT company_id (that
                // column is the lead's own organisation FK, a same-name
                // landmine, see class docblock).
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('crm_leads');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $total     = (clone $base)->count();
                    $converted = (clone $base)->where('status', 'converted')->count();
                    return $total > 0 ? round($converted / $total * 100, 1) : 22.5;
                }, 22.5),
            ],

            'cac' => [
                'label'          => 'CAC (Coût d\'acquisition client)',
                'unit'           => 'XOF',
                'direction'      => 'down',
                'formula'        => 'dépenses_commerciales / nouveaux_clients',
                'value_callback' => fn() => 85000.0, // requires marketing spend table — fallback
            ],

            'clv' => [
                'label'          => 'CLV (Valeur vie client)',
                'unit'           => 'XOF',
                'direction'      => 'up',
                'formula'        => 'valeur_moy_achat × fréquence × durée_relation',
                // sales_orders.tenant_id — real, populated from company_id
                // (same column SalesObjectiveService already filters on).
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('sales_orders')->where('status', 'confirmed');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $avgOrder = (float) (clone $base)->avg('total') ?? 0.0;
                    $ordersPerContact = (clone $base)
                        ->select('contact_id', DB::raw('count(*) as cnt'))
                        ->groupBy('contact_id')
                        ->get()
                        ->avg('cnt') ?? 1.0;
                    // Assume 3-year avg lifespan
                    return $avgOrder > 0 ? round($avgOrder * $ordersPerContact * 3, 0) : 1250000.0;
                }, 1250000.0),
            ],

            'win_rate' => [
                'label'          => 'Taux de transformation',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'opportunités_gagnées / opportunités_closes × 100',
                // crm_opportunities.tenant_id — real, populated from
                // company_id since Chantier 10's fix.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('crm_opportunities');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $closed = (clone $base)->whereIn('status', ['won', 'lost'])->count();
                    $won    = (clone $base)->where('status', 'won')->count();
                    return $closed > 0 ? round($won / $closed * 100, 1) : 38.2;
                }, 38.2),
            ],

            'pipeline_velocity' => [
                'label'          => 'Vélocité du pipeline',
                'unit'           => 'XOF/jour',
                'direction'      => 'up',
                'formula'        => '(opportunités × taux_win × montant_moy) / durée_cycle',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('crm_opportunities');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $opportunities = (clone $base)->where('status', 'open')->count();
                    $won    = (clone $base)->where('status', 'won')->count();
                    $closed = (clone $base)->whereIn('status', ['won', 'lost'])->count();
                    $winRate  = $closed > 0 ? $won / $closed : 0.38;
                    $avgDeal  = (float) ((clone $base)->where('status', 'won')->avg('amount') ?? 450000);
                    $avgCycle = 32.0; // days — would need date diff from creation to close
                    return $avgCycle > 0 ? round($opportunities * $winRate * $avgDeal / $avgCycle, 0) : 3250000.0;
                }, 3250000.0),
            ],
        ];
    }

    private function hrKPIs(?string $tenantId): array
    {
        return [
            'turnover_rate' => [
                'label'          => 'Taux de turnover',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'départs / effectif_moy × 100',
                // hr_employees.company_id — real (HR's Chantier 32.17 audit).
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hr_employees');
                    if ($tenantId !== null) {
                        $base->where('company_id', $tenantId);
                    }
                    $headcount   = (int) (clone $base)->where('status', 'active')->count();
                    $currentYear = Carbon::now()->year;
                    $departures  = (clone $base)
                        ->where('status', 'inactive')
                        ->whereYear('updated_at', $currentYear)
                        ->count();
                    return $headcount > 0 ? round($departures / $headcount * 100, 1) : 12.4;
                }, 12.4),
            ],

            'absenteeism_rate' => [
                'label'          => 'Taux d\'absentéisme',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'jours_absents / (jours_travail × effectif) × 100',
                // hr_employees.company_id real; hr_leave_requests has no
                // tenant column of its own, joined via employee_id.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $now = Carbon::now();

                    $employeeBase = DB::table('hr_employees');
                    if ($tenantId !== null) {
                        $employeeBase->where('company_id', $tenantId);
                    }
                    $headcount   = (int) (clone $employeeBase)->where('status', 'active')->count();
                    $workingDays = 22; // avg month

                    $leaveQuery = DB::table('hr_leave_requests')
                        ->where('status', 'approved')
                        ->whereYear('start_date', $now->year)
                        ->whereMonth('start_date', $now->month);
                    if ($tenantId !== null) {
                        $leaveQuery->whereIn('employee_id', (clone $employeeBase)->pluck('id'));
                    }
                    $absentDays = (float) $leaveQuery->sum(DB::raw('DATEDIFF(end_date, start_date) + 1'));

                    $totalDays = $headcount * $workingDays;
                    return $totalDays > 0 ? round($absentDays / $totalDays * 100, 1) : 3.8;
                }, 3.8),
            ],

            'revenue_per_employee' => [
                'label'          => 'CA par employé',
                'unit'           => 'XOF',
                'direction'      => 'up',
                'formula'        => 'CA_annuel / effectif',
                // hr_employees.company_id real; acc_invoices has no tenant
                // column (see class docblock) — revenue side stays unscoped.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $employeeBase = DB::table('hr_employees');
                    if ($tenantId !== null) {
                        $employeeBase->where('company_id', $tenantId);
                    }
                    $headcount = (int) (clone $employeeBase)->where('status', 'active')->count();
                    $revenue   = (float) DB::table('acc_invoices')
                        ->whereIn('status', ['paid', 'partial'])
                        ->whereYear('created_at', Carbon::now()->year)
                        ->sum('amount_paid');
                    return $headcount > 0 ? round($revenue / $headcount, 0) : 8500000.0;
                }, 8500000.0),
            ],

            'training_roi' => [
                'label'          => 'ROI Formation',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => '(gain_perf - coût_formation) / coût_formation × 100',
                'value_callback' => fn() => 145.0, // requires training cost table — fallback
            ],

            'time_to_fill' => [
                'label'          => 'Délai de recrutement',
                'unit'           => 'jours',
                'direction'      => 'down',
                'formula'        => 'moy jours ouverture → embauche',
                // hr_job_postings.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hr_job_postings')
                        ->where('status', 'filled')
                        ->whereNotNull('filled_at');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $avg = $base->avg(DB::raw('DATEDIFF(filled_at, created_at)'));
                    return $avg ?? 28.0;
                }, 28.0),
            ],
        ];
    }

    private function inventoryKPIs(?string $tenantId): array
    {
        return [
            'inventory_turnover' => [
                'label'          => 'Rotation des stocks',
                'unit'           => 'x',
                'direction'      => 'up',
                'formula'        => 'COGS / stock_moyen',
                // inventory_stock/inventory_stock_movements have no tenant
                // column of their own — filtered via product_id join to
                // inventory_products.company_id. Chantier 32.22 (Inventory
                // 14-layer deep audit): this used to filter on
                // inventory_products.tenant_id, a column tied to a
                // completely different mechanism (Product's own
                // BelongsToTenant/stancl-tenancy trait, keyed off
                // tenancy()->tenant — never populated by any real write
                // path, confirmed empirically always NULL) — not the same
                // "tenant_id" convention sales_orders.tenant_id uses
                // elsewhere in this file (there it's genuinely populated
                // with the real company_id value under a legacy column
                // name). $tenantId here is a stringified company_id
                // (Strategy's own tenantId() helper, fixed to company_id
                // back in Chantier 10/Pilotage), so every real product
                // this comparison ever matched was a coincidence at best —
                // in practice this ratio always fell back to its static
                // default. Fixed to the real company_id column added by
                // this same chantier.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $stockQuery = DB::table('inventory_stock')
                        ->join('inventory_products', 'inventory_stock.product_id', '=', 'inventory_products.id');
                    if ($tenantId !== null) {
                        $stockQuery->where('inventory_products.company_id', $tenantId);
                    }
                    $stockValue = (float) $stockQuery
                        ->sum(DB::raw('inventory_stock.quantity * COALESCE(inventory_products.cost_price, 0)'));

                    $cogsQuery = DB::table('inventory_stock_movements')
                        ->where('type', 'out')
                        ->whereYear('created_at', Carbon::now()->year);
                    if ($tenantId !== null) {
                        $cogsQuery->whereIn('product_id', DB::table('inventory_products')
                            ->where('company_id', $tenantId)
                            ->pluck('id'));
                    }
                    $cogs = (float) $cogsQuery->sum(DB::raw('quantity * COALESCE(unit_cost, 0)'));

                    return $stockValue > 0 ? round($cogs / $stockValue, 1) : 6.2;
                }, 6.2),
            ],

            'stockout_rate' => [
                'label'          => 'Taux de rupture de stock',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'produits_en_rupture / total_produits_actifs × 100',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $productBase = DB::table('inventory_products')->where('is_active', true);
                    if ($tenantId !== null) {
                        $productBase->where('company_id', $tenantId);
                    }
                    $total = (clone $productBase)->count();

                    $stockoutQuery = DB::table('inventory_stock')->where('quantity', '<=', 0);
                    if ($tenantId !== null) {
                        $stockoutQuery->whereIn('product_id', (clone $productBase)->pluck('id'));
                    }
                    $stockout = $stockoutQuery->count();

                    return $total > 0 ? round($stockout / $total * 100, 1) : 2.1;
                }, 2.1),
            ],

            'fill_rate' => [
                'label'          => 'Taux de service',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'commandes_servies_complètes / total_commandes × 100',
                // sales_orders.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('sales_orders');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $total     = (clone $base)->where('status', 'confirmed')->count();
                    $fulfilled = (clone $base)->where('status', 'delivered')->count();
                    return $total > 0 ? round($fulfilled / $total * 100, 1) : 94.7;
                }, 94.7),
            ],

            'carrying_cost_ratio' => [
                'label'          => 'Taux de coût de portage',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'coût_portage / valeur_stock_moy × 100',
                'value_callback' => fn() => 18.3, // requires warehouse cost allocation — fallback
            ],
        ];
    }

    private function salesKPIs(?string $tenantId): array
    {
        return [
            'revenue_growth_rate' => [
                'label'          => 'Taux de croissance CA',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => '(CA_N - CA_N-1) / CA_N-1 × 100',
                // sales_orders.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $now  = Carbon::now();
                    $base = DB::table('sales_orders')->where('status', 'confirmed');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $thisMonth = (float) (clone $base)
                        ->whereYear('created_at', $now->year)
                        ->whereMonth('created_at', $now->month)
                        ->sum('total');
                    $lastMonth = (float) (clone $base)
                        ->whereYear('created_at', $now->copy()->subMonth()->year)
                        ->whereMonth('created_at', $now->copy()->subMonth()->month)
                        ->sum('total');
                    return $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : 18.7;
                }, 18.7),
            ],

            'avg_deal_size' => [
                'label'          => 'Taille moyenne des commandes',
                'unit'           => 'XOF',
                'direction'      => 'up',
                'formula'        => 'CA_total / nombre_commandes',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('sales_orders')
                        ->where('status', 'confirmed')
                        ->whereYear('created_at', Carbon::now()->year);
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    return $base->avg('total') ?? 450000.0;
                }, 450000.0),
            ],

            'sales_cycle_length' => [
                'label'          => 'Durée du cycle de vente',
                'unit'           => 'jours',
                'direction'      => 'down',
                'formula'        => 'moy jours premier contact → commande confirmée',
                // crm_opportunities.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('crm_opportunities')
                        ->where('status', 'won')
                        ->whereNotNull('closed_at');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $avg = $base->avg(DB::raw('DATEDIFF(closed_at, created_at)'));
                    return $avg ?? 32.0;
                }, 32.0),
            ],

            'quota_attainment' => [
                'label'          => 'Atteinte des objectifs commerciaux',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'CA_réalisé / objectif × 100',
                'value_callback' => fn() => 87.5, // requires sales quota table — fallback
            ],
        ];
    }

    private function manufacturingKPIs(?string $tenantId): array
    {
        return [
            'oee' => [
                'label'          => 'TRS (Taux de Rendement Synthétique)',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'disponibilité × performance × qualité × 100',
                // mfg_production_orders.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('mfg_production_orders')
                        ->whereIn('status', ['completed', 'done'])
                        ->whereYear('created_at', Carbon::now()->year);
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $orders = $base->get(['planned_quantity', 'actual_quantity']);
                    if ($orders->isEmpty()) {
                        return 72.3;
                    }
                    $totalPlanned = $orders->sum('planned_quantity');
                    $totalActual  = $orders->sum('actual_quantity');
                    // Simplified OEE: actual / planned as performance proxy
                    return $totalPlanned > 0 ? round($totalActual / $totalPlanned * 100, 1) : 72.3;
                }, 72.3),
            ],

            'defect_rate' => [
                'label'          => 'Taux de défauts',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'unités_défectueuses / unités_produites × 100',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('mfg_production_orders')->whereIn('status', ['completed', 'done']);
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    $total   = (float) (clone $base)->sum('actual_quantity');
                    $defects = (float) (clone $base)->sum('rejected_quantity');
                    return $total > 0 ? round($defects / $total * 100, 1) : 1.8;
                }, 1.8),
            ],

            'production_efficiency' => [
                'label'          => 'Efficience de production',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'temps_standard / temps_réel × 100',
                'value_callback' => fn() => 85.4, // requires work center time tracking — fallback
            ],

            'scrap_rate' => [
                'label'          => 'Taux de rebut',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'coût_rebuts / coût_production_total × 100',
                'value_callback' => fn() => 3.2, // requires scrap cost entries — fallback
            ],
        ];
    }

    private function helpdeskKPIs(?string $tenantId): array
    {
        return [
            'first_response_time' => [
                'label'          => 'Délai de première réponse',
                'unit'           => 'min',
                'direction'      => 'down',
                'formula'        => 'moy minutes création ticket → première réponse',
                // hd_tickets.company_id — real (Helpdesk's Chantier 32.17 audit).
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hd_tickets')->whereNotNull('first_response_at');
                    if ($tenantId !== null) {
                        $base->where('company_id', $tenantId);
                    }
                    $avg = $base->avg(DB::raw('TIMESTAMPDIFF(MINUTE, created_at, first_response_at)'));
                    return $avg ?? 12.5;
                }, 12.5),
            ],

            'resolution_rate' => [
                'label'          => 'Taux de résolution',
                'unit'           => '%',
                'direction'      => 'up',
                'formula'        => 'tickets_résolus / total_tickets × 100',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hd_tickets')->whereYear('created_at', Carbon::now()->year);
                    if ($tenantId !== null) {
                        $base->where('company_id', $tenantId);
                    }
                    $total    = (clone $base)->count();
                    $resolved = (clone $base)->whereNotNull('resolved_at')->count();
                    return $total > 0 ? round($resolved / $total * 100, 1) : 91.2;
                }, 91.2),
            ],

            'csat_score' => [
                'label'          => 'Score de satisfaction client (CSAT)',
                'unit'           => '/5',
                'direction'      => 'up',
                'formula'        => 'moy scores satisfaction',
                // hd_chat_sessions.tenant_id — real.
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hd_chat_sessions')->whereNotNull('csat_score');
                    if ($tenantId !== null) {
                        $base->where('tenant_id', $tenantId);
                    }
                    return $base->avg('csat_score') ?? 4.2;
                }, 4.2),
            ],

            'escalation_rate' => [
                'label'          => 'Taux d\'escalade',
                'unit'           => '%',
                'direction'      => 'down',
                'formula'        => 'tickets_escaladés / total_tickets × 100',
                'value_callback' => fn() => $this->query(function () use ($tenantId) {
                    $base = DB::table('hd_tickets')->whereYear('created_at', Carbon::now()->year);
                    if ($tenantId !== null) {
                        $base->where('company_id', $tenantId);
                    }
                    $total     = (clone $base)->count();
                    $escalated = (clone $base)->where('is_escalated', true)->count();
                    return $total > 0 ? round($escalated / $total * 100, 1) : 8.7;
                }, 8.7),
            ],
        ];
    }
}
