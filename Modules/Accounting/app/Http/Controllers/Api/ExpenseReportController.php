<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\ExpenseReport;
use Modules\Accounting\Services\ExpenseService;

/**
 * @group Accounting - Expense Reports
 *
 * Manage expense reports: submit, approve, export, and analytics.
 */
class ExpenseReportController extends Controller
{
    public function __construct(private ExpenseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $reports = ExpenseReport::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json($reports);
    }

    public function show(ExpenseReport $expenseReport): JsonResponse
    {
        return response()->json(['data' => $expenseReport->load('lines')]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'title'       => 'required|string|max:255',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'currency'    => 'nullable|string|size:3',
        ]);

        $report = ExpenseReport::create($validated);

        return response()->json(['data' => $report], 201);
    }

    public function update(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'sometimes|string|max:255',
            'status'  => 'sometimes|string',
            'notes'   => 'nullable|string',
        ]);

        $expenseReport->update($validated);

        return response()->json(['data' => $expenseReport]);
    }

    public function destroy(ExpenseReport $expenseReport): JsonResponse
    {
        $expenseReport->delete();

        return response()->json(null, 204);
    }

    /** POST /expense-reports/{expenseReport}/submit */
    public function submit(ExpenseReport $expenseReport): JsonResponse
    {
        $this->service->submit($expenseReport);

        return response()->json(['data' => $expenseReport->fresh()]);
    }

    /** POST /expense-reports/{expenseReport}/approve */
    public function approve(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        $this->service->approve($expenseReport);

        return response()->json(['data' => $expenseReport->fresh()]);
    }

    /** GET /expense-reports/{expenseReport}/export */
    public function export(ExpenseReport $expenseReport, Request $request): JsonResponse
    {
        $format = $request->query('format', 'pdf');

        return response()->json([
            'data' => ['report_id' => $expenseReport->id, 'format' => $format, 'message' => 'Export queued.'],
        ]);
    }
}
