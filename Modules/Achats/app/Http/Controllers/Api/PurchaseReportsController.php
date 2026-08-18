<?php

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Invoice;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;

/**
 * @group Controllers - Purchase Reports
 *
 * Manage Purchase Reports resources.
 */
class PurchaseReportsController extends Controller
{
    /**
     * Real spend aggregation for the SpendAnalytics dashboard: total spend,
     * spend broken down by supplier (Achats has no product-category taxonomy
     * on POs, so "by category" is reconciled to "by supplier"), and a
     * monthly trend — all off real, non-cancelled PurchaseOrder totals.
     */
    public function spending(Request $request)
    {
        [$start, $end] = $this->resolvePeriod($request->get('period', 'ytd'));

        $baseQuery = PurchaseOrder::whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->whereBetween('order_date', [$start, $end]);

        $totalSpend = (float) (clone $baseQuery)->sum('total');

        $bySupplier = (clone $baseQuery)
            ->select('supplier_id', DB::raw('SUM(total) as amount'), DB::raw('COUNT(*) as order_count'))
            ->groupBy('supplier_id')
            ->with('supplier:id,name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier?->name ?? 'N/A',
                'amount' => (float) $row->amount,
                'order_count' => (int) $row->order_count,
                'percent' => $totalSpend > 0 ? round(($row->amount / $totalSpend) * 100, 1) : 0,
            ])
            ->values();

        $monthlyTrend = (clone $baseQuery)
            ->select(DB::raw("DATE_FORMAT(order_date, '%Y-%m') as ym"), DB::raw('SUM(total) as amount'))
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => ['month' => $row->ym, 'amount' => (float) $row->amount])
            ->values();

        $activeSuppliers = Supplier::where('is_active', true)->count();
        $newSuppliers = Supplier::where('is_active', true)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return response()->json([
            'period' => $request->get('period', 'ytd'),
            'total_spend' => $totalSpend,
            'active_suppliers' => $activeSuppliers,
            'new_suppliers' => $newSuppliers,
            'by_supplier' => $bySupplier,
            'monthly_trend' => $monthlyTrend,
        ]);
    }

    public function pendingReceipts()
    {
        $pos = PurchaseOrder::where('status', 'approved')
            ->whereDoesntHave('receipt')
            ->with('supplier')
            ->get();

        return $pos;
    }

    /**
     * Vendor-side overdue invoices (Accounting\Invoice, partner_type=vendor)
     * — uses the model's own real scopeOverdue() rather than reimplementing
     * the due-date/status logic.
     */
    public function overdueInvoices()
    {
        $invoices = Invoice::overdue()
            ->where('partner_type', 'vendor')
            ->orderBy('due_date')
            ->get(['id', 'number', 'partner_id', 'partner_name', 'due_date', 'total', 'amount_paid', 'amount_due', 'status']);

        return response()->json([
            'count' => $invoices->count(),
            'total_overdue' => (float) $invoices->sum(fn ($invoice) => $invoice->getOutstandingBalance()),
            'invoices' => $invoices,
        ]);
    }

    private function resolvePeriod(string $period): array
    {
        $now = Carbon::now();

        return match (true) {
            $period === 'q1' => [Carbon::create($now->year, 1, 1)->startOfDay(), Carbon::create($now->year, 3, 31)->endOfDay()],
            preg_match('/^\d{4}$/', $period) === 1 => [Carbon::create((int) $period, 1, 1)->startOfDay(), Carbon::create((int) $period, 12, 31)->endOfDay()],
            default => [$now->copy()->startOfYear(), $now->copy()->endOfDay()], // 'ytd'
        };
    }
}
