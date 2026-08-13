<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\TaxComplianceReport;

class TaxComplianceReportController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxComplianceReport::class);

        $reports = TaxComplianceReport::with(['company', 'jurisdiction'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('jurisdiction_id'), fn($q) => $q->where('tax_jurisdiction_id', $request->jurisdiction_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('period_from'), fn($q) => $q->whereDate('report_period_start', '>=', $request->period_from))
            ->when($request->filled('period_to'), fn($q) => $q->whereDate('report_period_end', '<=', $request->period_to))
            ->orderBy('report_period_start', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($reports);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', TaxComplianceReport::class);

        $validated = $request->validate([
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'report_period_start' => 'required|date',
            'report_period_end' => 'required|date|after:report_period_start',
            'tax_data' => 'nullable|json',
            'compliance_checks' => 'nullable|json',
            'notes' => 'nullable|string',
            'total_tax_liability' => 'nullable|numeric',
            'total_tax_paid' => 'nullable|numeric',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['status'] = 'draft';

        $report = TaxComplianceReport::create($validated);

        return response()->json($report, 201);
    }

    public function show(TaxComplianceReport $report): JsonResponse
    {
        $this->authorize('view', $report);

        return response()->json($report->load(['company', 'jurisdiction']));
    }

    public function update(Request $request, TaxComplianceReport $report): JsonResponse
    {
        $this->authorize('update', $report);

        $validated = $request->validate([
            'tax_data' => 'nullable|json',
            'compliance_checks' => 'nullable|json',
            'notes' => 'nullable|string',
            'total_tax_liability' => 'nullable|numeric',
            'total_tax_paid' => 'nullable|numeric',
            'status' => 'in:draft,prepared,reviewed,filed,approved',
        ]);

        if ($validated['total_tax_liability'] ?? null && $validated['total_tax_paid'] ?? null) {
            $validated['tax_due_or_refund'] = $validated['total_tax_liability'] - $validated['total_tax_paid'];
        }

        $report->update($validated);

        return response()->json($report);
    }

    public function file(Request $request, TaxComplianceReport $report): JsonResponse
    {
        $this->authorize('file', $report);

        $validated = $request->validate([
            'filing_reference_number' => 'required|string',
        ]);

        $report->update([
            'status' => 'filed',
            'filed_at' => now(),
            'filing_reference_number' => $validated['filing_reference_number'],
        ]);

        return response()->json($report);
    }

    public function destroy(TaxComplianceReport $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(null, 204);
    }
}
