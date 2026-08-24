<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Models\TaxJurisdiction;
use Modules\Accounting\Services\AdvancedTaxComplianceService;

class TaxComplianceReportController extends Controller
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

        if ($validated['total_tax_liability'] ?? null && $validated['total_tax_paid'] ?? null) {
            $validated['tax_due_or_refund'] = $validated['total_tax_liability'] - $validated['total_tax_paid'];
        }

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

    /**
     * Chantier 32.14: live tax calculation preview, non-persisting.
     *
     * Wires up Modules\Accounting\Services\AdvancedTaxComplianceService — a real, well-written
     * VAT/income-tax/transfer-pricing/deferred-tax calculator that was confirmed (via a repo-wide
     * grep) to have zero producer anywhere in the app despite its 4 backing models/tables
     * (TaxJurisdiction, TaxComplianceReport, TaxFilingTemplate, TaxComplianceRule) being real and
     * migrated. Gated on the same viewAny ability as index() — a calculation preview is a read,
     * not a mutation of any specific report.
     */
    public function calculate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxComplianceReport::class);

        // AdvancedTaxComplianceService extends Modules\Shared\Services\BaseService, whose
        // constructor requires a real int $companyId — a real bug found while wiring this
        // endpoint: Laravel's container cannot resolve this via method injection (there is no
        // scalar-parameter resolution for an arbitrary int), which is exactly why this service
        // had zero producers anywhere in the app despite being fully written and tested-worthy.
        // Instantiated manually here rather than relying on the container, matching the
        // fail-closed-not-500 convention already established throughout this app.
        $companyId = (int) ($request->user()->company_id ?? 0);
        if ($companyId <= 0) {
            return response()->json(['message' => 'Aucune société associée à ce compte.'], 422);
        }
        $service = new AdvancedTaxComplianceService($companyId);

        $validated = $request->validate([
            'tax_jurisdiction_id' => 'required|exists:tax_jurisdictions,id',
            'type' => 'required|in:vat,income_tax,transfer_price,deferred_tax',
            'base_amount' => 'required_if:type,vat|numeric|min:0',
            'customer_type' => 'nullable|string',
            'gross_income' => 'required_if:type,income_tax|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*' => 'numeric',
            // min:0.01 (not 0) — AdvancedTaxComplianceService::calculateTransferPrice() divides
            // by $cost when computing profit_margin; a real bug (division by zero) found while
            // wiring this endpoint, closed here rather than in the service itself.
            'cost' => 'required_if:type,transfer_price|numeric|min:0.01',
            'method' => 'nullable|string',
            'book_income' => 'required_if:type,deferred_tax|numeric',
            'taxable_income' => 'required_if:type,deferred_tax|numeric',
            'tax_rate' => 'required_if:type,deferred_tax|numeric',
        ]);

        $jurisdiction = TaxJurisdiction::findOrFail($validated['tax_jurisdiction_id']);

        $result = match ($validated['type']) {
            'vat' => $service->calculateVAT(
                $jurisdiction,
                (float) $validated['base_amount'],
                $validated['customer_type'] ?? 'domestic'
            ),
            'income_tax' => $service->calculateIncomeTax(
                $jurisdiction,
                (float) $validated['gross_income'],
                $validated['deductions'] ?? []
            ),
            'transfer_price' => $service->calculateTransferPrice(
                $jurisdiction,
                (float) $validated['cost'],
                $validated['method'] ?? 'cost_plus'
            ),
            'deferred_tax' => $service->calculateDeferredTax(
                $jurisdiction,
                (float) $validated['book_income'],
                (float) $validated['taxable_income'],
                (float) $validated['tax_rate']
            ),
        };

        return response()->json(['type' => $validated['type'], 'result' => $result]);
    }
}
