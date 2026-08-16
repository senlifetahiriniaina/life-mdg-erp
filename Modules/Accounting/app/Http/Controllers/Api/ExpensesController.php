<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\Expense;
use Modules\Accounting\Models\ExpenseReport;
use Modules\Accounting\Services\ExpenseService;

/**
 * @group Accounting - Expenses
 *
 * Employee expense submissions, approvals, reports, and analytics.
 */
class ExpensesController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('date')
            ->paginate(25);

        return response()->json($expenses);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(['data' => $expense->load('lines')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'  => 'required|integer',
            'date'         => 'required|date',
            'amount'       => 'required|numeric|min:0',
            'currency'     => 'nullable|string|size:3',
            'category'     => 'nullable|string|max:50',
            'description'  => 'nullable|string',
        ]);

        $expense = Expense::create($validated);

        return response()->json(['data' => $expense], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => 'sometimes|numeric|min:0',
            'category'    => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'status'      => 'sometimes|string',
        ]);

        $expense->update($validated);

        return response()->json(['data' => $expense]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(null, 204);
    }

    /** GET /expenses/pending */
    public function pending(): JsonResponse
    {
        $expenses = Expense::where('status', 'pending')->orderByDesc('created_at')->paginate(25);

        return response()->json($expenses);
    }

    /** GET /expenses/category/{category} */
    public function byCategory(string $category): JsonResponse
    {
        $expenses = Expense::where('category', $category)->orderByDesc('date')->paginate(25);

        return response()->json(['data' => $expenses, 'category' => $category]);
    }

    /** GET /expenses/analytics */
    public function analytics(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $byCategory = Expense::whereBetween('date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get();

        $total = Expense::whereBetween('date', [$from, $to])->sum('amount');

        return response()->json([
            'data' => [
                'period'      => ['from' => $from, 'to' => $to],
                'total'       => $total,
                'by_category' => $byCategory,
            ],
        ]);
    }

    /** GET /expense-reports */
    public function listReports(Request $request): JsonResponse
    {
        $reports = ExpenseReport::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($reports);
    }

    /** GET /expense-reports/{report} */
    public function showReport(ExpenseReport $report): JsonResponse
    {
        return response()->json(['data' => $report->load('lines')]);
    }

    /** GET /expense-reports/{report}/analytics */
    public function reportAnalytics(ExpenseReport $report): JsonResponse
    {
        $lines = $report->lines()->selectRaw('category, SUM(amount) as total')->groupBy('category')->get();

        return response()->json([
            'data' => [
                'report_id'   => $report->id,
                'total'       => $report->lines()->sum('amount'),
                'by_category' => $lines,
            ],
        ]);
    }

    /** POST /expenses/{expense}/approve */
    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $expense->update([
            'status'         => 'approved',
            'approved_at'    => now(),
            'approved_by_id' => $request->user()?->id,
        ]);

        return response()->json($expense->fresh());
    }

    /** POST /expense-reports */
    public function createReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'  => 'required|integer',
            'title'        => 'nullable|string|max:255',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $report = ExpenseReport::create(array_merge($validated, [
            'title'  => $validated['title'] ?? 'Note de frais '.$validated['period_start'],
            'status' => 'draft',
        ]));

        return response()->json($report, 201);
    }

    /** POST /expense-reports/{report}/add */
    public function addToReport(Request $request, ExpenseReport $report): JsonResponse
    {
        $validated = $request->validate([
            'date'        => 'required|date',
            'category'    => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount'      => 'required|numeric|min:0',
            'currency'    => 'nullable|string|size:3',
        ]);

        $line = $report->lines()->create($validated);

        return response()->json($line, 201);
    }

    /** POST /expense-reports/{report}/submit */
    public function submitReport(ExpenseReport $report): JsonResponse
    {
        $this->service->submit($report);

        return response()->json($report->fresh());
    }

    /** POST /expense-reports/{report}/reimburse */
    public function reimburse(ExpenseReport $report): JsonResponse
    {
        abort_unless($report->status === 'approved', 422, 'Only approved reports can be reimbursed.');

        $report->update(['status' => 'reimbursed']);

        return response()->json($report->fresh());
    }
}
