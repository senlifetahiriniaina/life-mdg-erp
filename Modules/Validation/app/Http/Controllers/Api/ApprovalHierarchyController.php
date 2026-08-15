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

    public function index()
    {
        return ApprovalHierarchy::where('is_active', true)->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:validation_approval_hierarchies',
            'description' => 'nullable|string',
            'company_id' => 'nullable|integer',
        ]);

        $data['is_active'] = true;

        $hierarchy = $this->service->createHierarchy($data);

        return response()->json($hierarchy, 201);
    }

    public function show(ApprovalHierarchy $approval_hierarchy)
    {
        return $approval_hierarchy->load('levels.approvers');
    }

    public function update(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
        $data = $request->validate([
            'name' => 'string|unique:validation_approval_hierarchies,name,'.$approval_hierarchy->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $updated = $this->service->updateHierarchy($approval_hierarchy, $data);

        return $updated;
    }

    public function destroy(ApprovalHierarchy $approval_hierarchy)
    {
        $approval_hierarchy->delete();

        return response()->noContent();
    }

    public function addLevel(Request $request, ApprovalHierarchy $approval_hierarchy)
    {
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
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $this->service->addLevelApprovers($hierarchy_level, $data['user_ids']);

        return response()->json(['message' => 'Approvers added successfully']);
    }
}
