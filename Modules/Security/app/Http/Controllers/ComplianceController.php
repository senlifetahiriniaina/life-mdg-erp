<?php

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Security\Models\ComplianceControl;
use Modules\Security\Models\ComplianceAudit;
use Modules\Security\Models\ComplianceViolation;

class ComplianceController extends Controller
{
    public function indexControls(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ComplianceControl::class);

        $framework = $request->input('framework');

        $controls = ComplianceControl::where('company_id', auth()->user()->company_id)
            ->when($framework, fn($q) => $q->where('framework', $framework))
            ->paginate($request->input('per_page', 20));

        return response()->json($controls);
    }

    public function storeControl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'framework' => 'required|in:SOX,HIPAA,PCI-DSS,GDPR',
            'control_id' => 'required|string',
            'control_name' => 'required|string|max:255',
            'control_description' => 'required|string',
            'control_type' => 'required|in:preventive,detective,corrective',
            'implementation_details' => 'nullable|array',
        ]);

        $control = ComplianceControl::create([
            'company_id' => auth()->user()->company_id,
            'implementation_status' => 'planned',
            ...$validated,
        ]);

        return response()->json($control, 201);
    }

    public function showControl(ComplianceControl $control): JsonResponse
    {
        $this->authorize('view', $control);

        $control->load('violations');

        return response()->json($control);
    }

    public function updateControl(Request $request, ComplianceControl $control): JsonResponse
    {
        $this->authorize('update', $control);

        $validated = $request->validate([
            'implementation_status' => 'in:planned,implemented,verified,failed',
            'implementation_details' => 'array',
        ]);

        $control->update($validated);

        return response()->json($control);
    }

    public function verifyControl(ComplianceControl $control): JsonResponse
    {
        $this->authorize('verify', $control);

        $control->update([
            'implementation_status' => 'verified',
            'last_verified_at' => now(),
        ]);

        return response()->json($control);
    }

    public function deleteControl(ComplianceControl $control): JsonResponse
    {
        $this->authorize('delete', $control);

        $control->delete();

        return response()->json(null, 204);
    }

    public function indexAudits(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ComplianceAudit::class);

        $status = $request->input('status');

        $audits = ComplianceAudit::where('company_id', auth()->user()->company_id)
            ->when($status, fn($q) => $q->where('audit_status', $status))
            ->latest('audit_start_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($audits);
    }

    public function storeAudit(Request $request): JsonResponse
    {
        $this->authorize('create', ComplianceAudit::class);

        $validated = $request->validate([
            'audit_type' => 'required|in:scheduled,on_demand,incident_response',
            'framework' => 'required|in:SOX,HIPAA,PCI-DSS,GDPR',
        ]);

        $audit = ComplianceAudit::create([
            'company_id' => auth()->user()->company_id,
            'audit_status' => 'in_progress',
            'audit_start_date' => now(),
            ...$validated,
        ]);

        return response()->json($audit, 201);
    }

    public function showAudit(ComplianceAudit $audit): JsonResponse
    {
        $this->authorize('view', $audit);

        return response()->json($audit);
    }

    public function updateAudit(Request $request, ComplianceAudit $audit): JsonResponse
    {
        $this->authorize('update', $audit);

        $validated = $request->validate([
            'controls_evaluated' => 'integer|min:0',
            'controls_compliant' => 'integer|min:0',
            'controls_non_compliant' => 'integer|min:0',
            'compliance_score' => 'numeric|between:0,100',
            'findings' => 'array',
        ]);

        $audit->update($validated);

        return response()->json($audit);
    }

    public function completeAudit(Request $request, ComplianceAudit $audit): JsonResponse
    {
        $this->authorize('complete', $audit);

        $validated = $request->validate([
            'compliance_score' => 'required|numeric|between:0,100',
            'findings' => 'array',
        ]);

        $audit->update([
            'audit_status' => 'completed',
            'audit_end_date' => now(),
            ...$validated,
        ]);

        return response()->json($audit);
    }

    public function deleteAudit(ComplianceAudit $audit): JsonResponse
    {
        $this->authorize('delete', $audit);

        $audit->delete();

        return response()->json(null, 204);
    }

    public function indexViolations(Request $request): JsonResponse
    {
        $status = $request->input('status');

        $violations = ComplianceViolation::where('company_id', auth()->user()->company_id)
            ->when($status, fn($q) => $q->where('violation_status', $status))
            ->latest('detected_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($violations);
    }

    public function showViolation(ComplianceViolation $violation): JsonResponse
    {
        if ($violation->company_id !== auth()->user()->company_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($violation);
    }

    public function updateViolation(Request $request, ComplianceViolation $violation): JsonResponse
    {
        $validated = $request->validate([
            'violation_status' => 'in:open,remediated,waived,closed',
            'remediation_notes' => 'string',
        ]);

        $violation->update($validated);

        return response()->json($violation);
    }
}
