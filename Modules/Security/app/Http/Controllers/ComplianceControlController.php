<?php

declare(strict_types=1);

namespace Modules\Security\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Security\Models\ComplianceControl;

/**
 * @group Security - Compliance Controls
 *
 * CRUD for compliance controls and status tracking.
 */
class ComplianceControlController extends Controller
{
    /**
     * List compliance controls.
     *
     * @queryParam framework string Filter by framework (GDPR|OHADA|OWASP|ISO27001). Example: GDPR
     * @queryParam status string Filter by status (compliant|non_compliant|in_progress). Example: compliant
     */
    public function index(Request $request): JsonResponse
    {
        $controls = ComplianceControl::query()
            ->when($request->filled('framework'), fn ($q) => $q->where('framework', $request->framework))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($controls);
    }

    /**
     * Get a single compliance control.
     */
    public function show(ComplianceControl $complianceControl): JsonResponse
    {
        return response()->json(['data' => $complianceControl]);
    }

    /**
     * Create a compliance control.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'framework'   => 'required|string|max:100',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status'      => 'required|in:compliant,non_compliant,in_progress,not_applicable',
            'owner_id'    => 'nullable|integer|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $control = ComplianceControl::create($validated);

        return response()->json(['data' => $control, 'message' => 'Compliance control created'], 201);
    }

    /**
     * Update a compliance control.
     */
    public function update(Request $request, ComplianceControl $complianceControl): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'framework'   => 'sometimes|string|max:100',
            'category'    => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:compliant,non_compliant,in_progress,not_applicable',
            'owner_id'    => 'nullable|integer|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $complianceControl->update($validated);

        return response()->json(['data' => $complianceControl, 'message' => 'Compliance control updated']);
    }

    /**
     * Delete a compliance control.
     */
    public function destroy(ComplianceControl $complianceControl): JsonResponse
    {
        $complianceControl->delete();

        return response()->json(['message' => 'Compliance control deleted']);
    }

    /**
     * Mark a compliance control as verified.
     */
    public function verify(Request $request, ComplianceControl $complianceControl): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $complianceControl->update([
            'status'      => 'compliant',
            'verified_at' => now(),
            'verified_by' => $request->user()?->id,
            'notes'       => $validated['notes'] ?? $complianceControl->notes,
        ]);

        return response()->json(['data' => $complianceControl, 'message' => 'Control verified as compliant']);
    }

    /**
     * Summary by framework.
     */
    public function frameworkSummary(): JsonResponse
    {
        $summary = ComplianceControl::query()
            ->selectRaw('framework, status, COUNT(*) as count')
            ->groupBy('framework', 'status')
            ->get()
            ->groupBy('framework');

        return response()->json(['data' => $summary]);
    }
}
