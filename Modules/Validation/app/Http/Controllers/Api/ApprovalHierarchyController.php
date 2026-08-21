<?php

namespace Modules\Validation\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Services\ApprovalHierarchyService;

/**
 * @group Controllers - Approval Hierarchy
 *
 * Manage Approval Hierarchy resources.
 */
class ApprovalHierarchyController extends Controller
{
    public function __construct(protected ApprovalHierarchyService $service) {}

    /**
     * Chantier 31: validation_approval_hierarchies.company_id has existed
     * since 2026_08_14_000002 (ApprovalHierarchyService::getHierarchyByCompany()
     * already reads it) — real design intent to scope a hierarchy to one
     * company — but no controller method here ever filtered/checked it,
     * confirmed empirically via a real cross-company HTTP request: any
     * admin could list, view, and even successfully UPDATE another
     * company's hierarchy by id. A NULL company_id is the deliberate
     * "global/shared config" marker used by Achats'
     * ApprovalRoutingService::createDefaultWorkflows() for its seeded
     * default hierarchies — those stay visible/editable to every company's
     * admin, matching this app's established shared-config-resource
     * precedent (ValidationRule, acc_operation_templates, ...); only a
     * hierarchy explicitly scoped to ONE company (a non-NULL company_id)
     * is now denied to every other company.
     */
    private function assertVisible(Request $request, ApprovalHierarchy $hierarchy): void
    {
        if ($request->user()->hasRole('super-admin')) {
            return;
        }

        abort_if(
            $hierarchy->company_id !== null && (int) $hierarchy->company_id !== (int) $request->user()->company_id,
            404
        );
    }

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        return ApprovalHierarchy::where('is_active', true)
            ->when(
                ! $request->user()->hasRole('super-admin'),
                fn ($q) => $q->where(fn ($q2) => $q2->whereNull('company_id')->orWhere('company_id', $companyId))
            )
            ->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:validation_approval_hierarchies',
            'description' => 'nullable|string',
        ]);

        $data['is_active'] = true;
        // Chantier 31: company_id is never client-controlled — a new
        // hierarchy is scoped to its creator's own company by default. A
        // super-admin (whose own company_id is typically null) creating one
        // naturally lands NULL, i.e. global/shared, matching that role's
        // platform-operator scope everywhere else in this app.
        $data['company_id'] = $request->user()->company_id;

        $hierarchy = $this->service->createHierarchy($data);

        return response()->json($hierarchy, 201);
    }

    public function show(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
        $this->assertVisible($request, $approval_hierarchy);

        return $approval_hierarchy->load('levels.approvers');
    }

    public function update(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
        $this->assertVisible($request, $approval_hierarchy);

        $data = $request->validate([
            'name' => 'string|unique:validation_approval_hierarchies,name,'.$approval_hierarchy->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $updated = $this->service->updateHierarchy($approval_hierarchy, $data);

        return $updated;
    }

    public function destroy(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
        $this->assertVisible($request, $approval_hierarchy);

        $approval_hierarchy->delete();

        return response()->noContent();
    }

    public function addLevel(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
        $this->assertVisible($request, $approval_hierarchy);

        $data = $request->validate([
            'title' => 'required|string',
            'approver_count' => 'required|integer|min:1',
            'delegation_allowed' => 'boolean',
        ]);

        $data['level_order'] = $approval_hierarchy->levels()->max('level_order') + 1;

        $level = $this->service->addLevel($approval_hierarchy, $data);

        return response()->json($level, 201);
    }

    public function addLevelApprovers(Request $request, ApprovalHierarchy $approval_hierarchy, HierarchyLevel $hierarchy_level)
    {
        $this->assertVisible($request, $approval_hierarchy);

        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $this->service->addLevelApprovers($hierarchy_level, $data['user_ids']);

        return response()->json(['message' => 'Approvers added successfully']);
    }
}
