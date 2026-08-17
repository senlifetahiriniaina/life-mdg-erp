<?php

namespace Modules\Validation\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalWorkflowService;

/**
 * @group Controllers - Approval Workflow
 *
 * Manage Approval Workflow resources.
 */
class ApprovalWorkflowController extends Controller
{
    public function __construct(protected ApprovalWorkflowService $service) {}

    public function index(Request $request)
    {
        // Not hard-filtered to is_active=true: the workflow management screen
        // needs to list inactive workflows too, otherwise deactivating one
        // makes it permanently invisible/unreactivatable.
        $workflows = ApprovalWorkflow::query()
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->query('module_name'), fn ($q, $module) => $q->where('module_name', $module))
            ->with('rules')
            ->paginate(15);

        return $workflows;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|unique:validation_approval_workflows',
            'description' => 'nullable|string',
            'module_name' => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();
        $data['is_active'] = true;

        $workflow = $this->service->createWorkflow($data);

        return response()->json($workflow, 201);
    }

    public function show(ApprovalWorkflow $approval_workflow)
    {
        return $approval_workflow->load('rules');
    }

    public function update(Request $request, ApprovalWorkflow $approval_workflow)
    {
        $data = $request->validate([
            'name' => 'required|unique:validation_approval_workflows,name,'.$approval_workflow->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $updated = $this->service->updateWorkflow($approval_workflow, $data);

        return $updated;
    }

    public function destroy(ApprovalWorkflow $approval_workflow)
    {
        $approval_workflow->delete();

        return response()->noContent();
    }

    public function cloneWorkflow(Request $request, ApprovalWorkflow $approval_workflow)
    {
        $newName = $request->validate(['name' => 'required|unique:validation_approval_workflows'])['name'];

        $cloned = $this->service->cloneWorkflow($approval_workflow, $newName);

        return response()->json($cloned, 201);
    }
}
