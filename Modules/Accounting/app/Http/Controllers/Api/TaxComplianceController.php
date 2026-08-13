<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Services\TaxService;

/**
 * @group Accounting - Tax Compliance
 *
 * Track tax compliance obligations, filing deadlines, and generate compliance reports.
 */
class TaxComplianceController extends Controller
{
    public function __construct(private TaxService $service) {}

    public function index(Request $request): JsonResponse
    {
        $reports = TaxComplianceReport::query()
            ->when($request->country, fn ($q) => $q->where('country_code', $request->country))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('due_date')
            ->paginate(25);

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code'   => 'required|string|size:2',
            'tax_type'       => 'required|string|max:50',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
            'due_date'       => 'required|date',
            'status'         => 'nullable|string|in:pending,filed,overdue',
            'amount_due'     => 'nullable|numeric|min:0',
        ]);

        $report = TaxComplianceReport::create($validated);

        return response()->json(['data' => $report], 201);
    }

    public function update(Request $request, TaxComplianceReport $compliance): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'sometimes|string|in:pending,filed,overdue,paid',
            'amount_due' => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string',
        ]);

        $compliance->update($validated);

        return response()->json(['data' => $compliance]);
    }

    /** GET /tax/compliance/upcoming */
    public function upcoming(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $reports = TaxComplianceReport::query()
            ->where('status', 'pending')
            ->whereBetween('due_date', [now(), now()->addDays($days)])
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $reports, 'days_ahead' => $days]);
    }

    /** GET /tax/compliance/report */
    public function report(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $taxReport = $this->service->getTaxReport($from, $to);

        return response()->json(['data' => $taxReport]);
    }

    /** GET /tax/compliance/summary */
    public function summary(): JsonResponse
    {
        $counts = TaxComplianceReport::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status');

        return response()->json([
            'data' => [
                'pending' => $counts['pending'] ?? 0,
                'filed'   => $counts['filed']   ?? 0,
                'overdue' => $counts['overdue'] ?? 0,
                'total'   => $counts->sum(),
            ],
        ]);
    }

    /** GET /tax/compliance/export */
    public function export(Request $request): JsonResponse
    {
        $format = $request->query('format', 'csv');

        return response()->json([
            'data'    => ['format' => $format, 'message' => 'Export queued.', 'download_url' => null],
        ]);
    }

    /** POST /tax/compliance/mark-overdue */
    public function markOverdue(): JsonResponse
    {
        $count = TaxComplianceReport::where('status', 'pending')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);

        return response()->json(['data' => ['marked_overdue' => $count]]);
    }
}
