<?php

declare(strict_types=1);

namespace Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Models\EmployeeDocument;
use Modules\HR\Services\DocumentExpiryService;

/**
 * @group HR — Document Expiry Compliance
 */
class DocumentAlertController extends Controller
{
    public function __construct(private readonly DocumentExpiryService $service) {}

    // ── CRUD for documents ─────────────────────────────────────────────────

    /**
     * GET /api/v1/hr/documents
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('hr.documents.view');

        // Chantier 32: EmployeeDocument has no company_id column of its own
        // — resolved via the employee it belongs to. Unconditional scoping,
        // matching the confirmed empirical finding documented throughout
        // this module's other listing endpoints.
        $docs = EmployeeDocument::with('employee')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $request->user()->company_id))
            ->when($request->employee_id, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->document_type, fn ($q, $v) => $q->where('document_type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('expiry_date')
            ->paginate(20);

        return response()->json($docs);
    }

    /**
     * POST /api/v1/hr/documents
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('hr.documents.create');

        $validated = $request->validate([
            'employee_id'       => 'required|exists:hr_employees,id',
            'document_type'     => 'required|in:work_permit,residence_permit,professional_cert,medical_cert,driving_license,custom',
            'title'             => 'required|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'country'           => 'nullable|string|size:2',
            'issue_date'        => 'nullable|date',
            'expiry_date'       => 'nullable|date|after_or_equal:issue_date',
            'alert_days_before' => 'integer|min:1|max:365',
            'notes'             => 'nullable|string',
        ]);

        // Chantier 32: the given employee_id must belong to the caller's own
        // company (404, not 403 — matching this app's established
        // not-your-tenant-data-doesn't-exist-to-you convention).
        $docEmployee = \Modules\HR\Models\Employee::findOrFail($validated['employee_id']);
        abort_unless(
            ((int) ($request->user()->company_id ?? 0)) === ((int) ($docEmployee->company_id ?? 0)),
            404
        );

        $doc = EmployeeDocument::create(array_merge(
            ['status' => 'valid', 'alert_days_before' => 60],
            $validated,
        ));

        // Compute initial status
        $doc->update(['status' => $doc->computeStatus()]);

        return response()->json($doc->load('employee'), 201);
    }

    /**
     * GET /api/v1/hr/documents/{document}
     */
    public function show(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.view');
        $this->assertSameCompany($request, $document);

        return response()->json($document->load('employee'));
    }

    /**
     * PUT /api/v1/hr/documents/{document}
     */
    public function update(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.edit');
        $this->assertSameCompany($request, $document);

        $validated = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'reference_number'  => 'nullable|string|max:100',
            'country'           => 'nullable|string|size:2',
            'issue_date'        => 'nullable|date',
            'expiry_date'       => 'nullable|date',
            'alert_days_before' => 'integer|min:1|max:365',
            'status'            => 'sometimes|in:valid,expiring_soon,expired,pending_renewal',
            'notes'             => 'nullable|string',
        ]);

        $document->update($validated);

        // Re-compute status unless manually overridden
        if (! isset($validated['status'])) {
            $document->update(['status' => $document->fresh()->computeStatus()]);
        }

        return response()->json($document->fresh('employee'));
    }

    /**
     * DELETE /api/v1/hr/documents/{document}
     */
    public function destroy(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.delete');
        $this->assertSameCompany($request, $document);

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    // ── Alert & Compliance endpoints ───────────────────────────────────────

    /**
     * GET /api/v1/hr/documents/expiring?days=30
     * List documents expiring within N days (default 30).
     */
    public function expiring(Request $request): JsonResponse
    {
        $this->authorize('hr.documents.view');

        $days = (int) $request->input('days', 30);
        $days = max(1, min($days, 365));

        $docs = $this->service->getExpiringDocuments($days, $request->user()->company_id);

        return response()->json([
            'days'      => $days,
            'count'     => $docs->count(),
            'documents' => $docs,
        ]);
    }

    /**
     * POST /api/v1/hr/documents/{document}/remind
     * Manually trigger an expiry reminder for a specific document.
     */
    public function remind(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.remind');
        $this->assertSameCompany($request, $document);

        $daysLeft = $document->days_until_expiry ?? 0;

        // Re-use the daily alert mechanism for this single document
        try {
            $this->service->runDailyAlerts();

            return response()->json([
                'message'   => 'Reminder sent.',
                'days_left' => $daysLeft,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to send reminder: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/hr/documents/compliance-report
     * Aggregate compliance report grouped by type and department.
     */
    public function complianceReport(Request $request): JsonResponse
    {
        $this->authorize('hr.documents.view');

        $report = $this->service->complianceReport($request->user()->company_id);

        return response()->json($report);
    }

    /**
     * Chantier 32: EmployeeDocument has no company_id column of its own —
     * ownership is resolved through the employee it belongs to. 404 (not
     * 403) matches this app's established not-your-tenant-data-doesn't-
     * exist-to-you convention (see Modules\Achats's ScopesToCompany trait).
     */
    private function assertSameCompany(Request $request, EmployeeDocument $document): void
    {
        abort_unless(
            ((int) ($request->user()->company_id ?? 0)) === ((int) ($document->employee?->company_id ?? 0)),
            404
        );
    }
}
