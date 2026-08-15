<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Core\Models\ApprovalInstance;
use Modules\Core\Models\ApprovalWorkflow;
use Modules\Core\Services\ApprovalService;

/**
 * @group Core - Approvals
 *
 * Global multi-level approval workflow engine usable across all modules.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $service)
    {
        $this->middleware('auth:sanctum');
    }

    // ─── Workflows ────────────────────────────────────────────────────────────

    /**
     * List all approval workflows.
     *
     * GET /api/v1/core/approvals/workflows
     */
    public function indexWorkflows(Request $request): JsonResponse
    {
        $query = ApprovalWorkflow::query()->orderBy('module')->orderBy('name');

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->paginate(50));
    }

    /**
     * Create a new approval workflow.
     *
     * POST /api/v1/core/approvals/workflows
     */
    public function storeWorkflow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'module' => 'required|string|max:50',
            'resource_type' => 'required|string|max:100',
            'steps' => 'required|array|min:1',
            'steps.*.order' => 'required|integer|min:1',
            'steps.*.label' => 'required|string',
            'steps.*.approver_type' => 'required|string|in:user,role,department',
            'steps.*.approver_value' => 'required|string',
            'is_active' => 'boolean',
            'allow_parallel' => 'boolean',
        ]);

        $workflow = ApprovalWorkflow::create(array_merge($validated, [
            'created_by' => $request->user()->id,
        ]));

        return response()->json($workflow, 201);
    }

    /**
     * Show a single approval workflow.
     *
     * GET /api/v1/core/approvals/workflows/{id}
     */
    public function showWorkflow(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        return response()->json($approvalWorkflow->load('createdBy'));
    }

    /**
     * Update an approval workflow.
     *
     * PUT /api/v1/core/approvals/workflows/{id}
     */
    public function updateWorkflow(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'module' => 'sometimes|string|max:50',
            'resource_type' => 'sometimes|string|max:100',
            'steps' => 'sometimes|array|min:1',
            'steps.*.order' => 'required_with:steps|integer|min:1',
            'steps.*.label' => 'required_with:steps|string',
            'steps.*.approver_type' => 'required_with:steps|string|in:user,role,department',
            'steps.*.approver_value' => 'required_with:steps|string',
            'is_active' => 'boolean',
            'allow_parallel' => 'boolean',
        ]);

        $approvalWorkflow->update($validated);

        return response()->json($approvalWorkflow->fresh());
    }

    /**
     * Delete an approval workflow.
     *
     * DELETE /api/v1/core/approvals/workflows/{id}
     */
    public function destroyWorkflow(ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $approvalWorkflow->delete();

        return response()->json(null, 204);
    }

    // ─── Instances ────────────────────────────────────────────────────────────

    /**
     * Get the approval instance for a subject.
     *
     * GET /api/v1/core/approvals/instances/{subject_type}/{subject_id}
     */
    public function getInstance(string $subjectType, int $subjectId): JsonResponse
    {
        $instance = ApprovalInstance::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with(['workflow', 'decisions.approver', 'initiatedBy'])
            ->latest()
            ->first();

        if (! $instance) {
            return response()->json(['message' => 'No approval instance found for this subject.'], 404);
        }

        return response()->json($instance);
    }

    /**
     * Submit a decision (approve / reject / escalate) on an instance.
     *
     * POST /api/v1/core/approvals/instances/{id}/decide
     */
    public function decide(Request $request, ApprovalInstance $approvalInstance): JsonResponse
    {
        $validated = $request->validate([
            'decision' => 'required|string|in:approved,rejected,escalated',
            'comment' => 'nullable|string',
        ]);

        if (! $this->service->canApprove($approvalInstance, $request->user())) {
            return response()->json(['message' => 'You are not authorised to approve this step.'], 403);
        }

        $decision = $this->service->submitDecision(
            $approvalInstance,
            $request->user(),
            $validated['decision'],
            $validated['comment'] ?? null
        );

        return response()->json([
            'decision' => $decision,
            'instance' => $approvalInstance->fresh(),
        ]);
    }

    /**
     * Cancel an approval instance.
     *
     * POST /api/v1/core/approvals/instances/{id}/cancel
     */
    public function cancel(Request $request, ApprovalInstance $approvalInstance): JsonResponse
    {
        $this->service->cancelApproval($approvalInstance, $request->user());

        return response()->json($approvalInstance->fresh());
    }

    /**
     * List pending approvals where the authenticated user is the next approver.
     *
     * GET /api/v1/core/approvals/pending
     *
     * Optimized: Uses eager loading to avoid N+1 queries
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRoles = $user->roles()->pluck('id')->toArray();

        // Eager load all relationships to avoid N+1 queries
        $instances = ApprovalInstance::where('status', 'pending')
            ->with([
                'workflow',
                'decisions' => fn ($q) => $q->with('approver'),
                'initiatedBy',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Filter using cached role/user data (no additional queries)
        $filteredInstances = $instances->filter(
            fn (ApprovalInstance $instance) =>
                $this->service->canApproveWithRoles($instance, $user, $userRoles)
        )->values();

        return response()->json([
            'data' => $filteredInstances,
            'pagination' => [
                'total' => $instances->total(),
                'per_page' => $instances->perPage(),
                'current_page' => $instances->currentPage(),
                'last_page' => $instances->lastPage(),
            ],
        ]);
    }
}
