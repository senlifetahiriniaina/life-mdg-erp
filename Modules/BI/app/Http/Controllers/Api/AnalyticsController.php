<?php

declare(strict_types=1);

namespace Modules\BI\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group BI - Analytics
 *
 * Pre-computed chart-ready analytics for the ERP dashboard.
 */
class AnalyticsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $months = max(1, min((int) ($request->months ?? 6), 24));
        $from = Carbon::now()->subMonths($months - 1)->startOfMonth();

        return response()->json([
            'revenue' => $this->revenueByMonth($from, $months),
            'tickets' => $this->ticketsByStatus(),
            'leads' => $this->leadsByStage(),
            'top_products' => $this->topProducts(),
            'kpi_snapshot' => $this->kpiSnapshot(),
        ]);
    }

    // ── Revenue ────────────────────────────────────────────────────────

    private function revenueByMonth(Carbon $from, int $months): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at) as month"
            : "DATE_FORMAT(created_at, '%Y-%m') as month";

        $rows = DB::table('acc_invoices')
            ->where('status', 'paid')
            ->where('created_at', '>=', $from)
            ->selectRaw("{$dateExpr}, SUM(total) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $labels = [];
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = Carbon::now()->subMonths($i)->format('Y-m');
            $labels[] = Carbon::now()->subMonths($i)->format('M Y');
            $data[] = round((float) ($rows[$key]->total ?? 0), 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    // ── Helpdesk tickets ───────────────────────────────────────────────

    private function ticketsByStatus(): array
    {
        $rows = DB::table('hd_tickets')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [$r->status => (int) $r->count])->all();
    }

    // ── CRM leads funnel ──────────────────────────────────────────────

    private function leadsByStage(): array
    {
        $driver = DB::connection()->getDriverName();
        $orderExpr = $driver === 'sqlite'
            ? "CASE status WHEN 'new' THEN 1 WHEN 'contacted' THEN 2 WHEN 'qualified' THEN 3 WHEN 'converted' THEN 4 WHEN 'lost' THEN 5 ELSE 6 END"
            : "FIELD(status, 'new','contacted','qualified','converted','lost')";

        $rows = DB::table('crm_leads')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByRaw($orderExpr)
            ->get();

        return [
            'labels' => $rows->pluck('status')->map(fn ($s) => ucfirst($s))->values()->all(),
            'data' => $rows->pluck('count')->map(fn ($c) => (int) $c)->values()->all(),
        ];
    }

    // ── Top products by revenue ───────────────────────────────────────

    private function topProducts(int $limit = 5): array
    {
        // Chantier 9: was querying `pos_order_items`, a stub table scaffolded
        // for the POS module — which is explicitly out of Life MDG's 27-module
        // scope (no Modules/POS exists) and never had real order data. Repointed
        // at the real, in-scope Sales module's order lines instead.
        $rows = DB::table('sales_order_lines as oi')
            ->join('inventory_products as p', 'p.id', '=', 'oi.product_id')
            ->selectRaw('p.name, SUM(oi.line_total) as revenue, SUM(oi.quantity) as units')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'revenue' => round((float) $r->revenue, 2),
            'units' => (int) $r->units,
        ])->values()->all();
    }

    // ── KPI snapshot ─────────────────────────────────────────────────

    private function kpiSnapshot(): array
    {
        $openTickets = (int) DB::table('hd_tickets')->where('status', 'open')->count();
        $prevTickets = (int) DB::table('hd_tickets')
            ->where('status', 'open')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        $overdue = (int) DB::table('acc_invoices')->where('status', 'overdue')->count();
        $employees = (int) DB::table('hr_employees')->where('status', 'active')->count();
        $newLeads = (int) DB::table('crm_leads')->where('created_at', '>=', now()->subDays(30))->count();
        $prevLeads = (int) DB::table('crm_leads')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->count();
        $lowStock = (int) DB::query()
            ->fromSub(
                DB::table('inventory_products')
                    ->join('inventory_stock', 'inventory_products.id', '=', 'inventory_stock.product_id')
                    ->where('inventory_products.is_active', true)
                    ->where('inventory_products.reorder_point', '>', 0)
                    ->groupBy('inventory_products.id', 'inventory_products.reorder_point')
                    ->havingRaw('SUM(inventory_stock.quantity) <= inventory_products.reorder_point')
                    ->selectRaw('inventory_products.id'),
                'low_stock'
            )
            ->count();

        $leadTrend = $newLeads > $prevLeads ? 'up' : ($newLeads < $prevLeads ? 'down' : 'flat');
        $leadChange = $prevLeads > 0
            ? abs(round(($newLeads - $prevLeads) / $prevLeads * 100)).'%'
            : ($newLeads > 0 ? '+'.$newLeads : '—');

        return [
            ['label' => 'Open Tickets',    'value' => (string) $openTickets, 'trend' => $openTickets <= $prevTickets ? 'down' : 'up',  'change' => abs($openTickets - $prevTickets).' vs last week'],
            ['label' => 'Overdue Invoices', 'value' => (string) $overdue,     'trend' => $overdue === 0 ? 'flat' : 'up',                'change' => $overdue > 0 ? 'Requires action' : 'All clear'],
            ['label' => 'Active Employees', 'value' => (string) $employees,   'trend' => 'flat',                                        'change' => 'Headcount'],
            ['label' => 'New Leads (30d)', 'value' => (string) $newLeads,    'trend' => $leadTrend,                                    'change' => $leadChange],
            ['label' => 'Low Stock Items', 'value' => (string) $lowStock,    'trend' => $lowStock === 0 ? 'flat' : 'up',               'change' => $lowStock > 0 ? 'Reorder needed' : 'Stock healthy'],
        ];
    }
}
