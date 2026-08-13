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

        $docs = EmployeeDocument::with('employee')
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
    public function show(EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.view');

        return response()->json($document->load('employee'));
    }

    /**
     * PUT /api/v1/hr/documents/{document}
     */
    public function update(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.edit');

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
    public function destroy(EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.delete');

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

        $docs = $this->service->getExpiringDocuments($days);

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
    public function remind(EmployeeDocument $document): JsonResponse
    {
        $this->authorize('hr.documents.remind');

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
    public function complianceReport(): JsonResponse
    {
        $this->authorize('hr.documents.view');

        $report = $this->service->complianceReport();

        return response()->json($report);
    }
}
