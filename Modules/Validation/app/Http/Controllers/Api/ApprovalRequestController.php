<?php

namespace Modules\Validation\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;
use Modules\Validation\Services\ApprovalRequestService;

/**
 * @group Controllers - Approval Request
 *
 * Manage Approval Request resources.
 */
class ApprovalRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected ApprovalRequestService $service) {}

    public function index(Request $request)
    {
        $query = ApprovalRequest::with(['workflow', 'requester', 'approver']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('for_user')) {
            $userId = $request->for_user === 'me' ? auth()->id() : $request->for_user;
            $query->where('requested_by', $userId);
        }

        $requests = $query->paginate(15);

        return $requests;
    }

    public function store(Request $request)
    {
        $this->authorize('create', ApprovalRequest::class);

        $data = $request->validate([
            // Allowlisted alias (see ValidationServiceProvider::registerApprovableMorphMap),
            // never a raw class name from client input.
            'approvable_type' => ['required', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'approvable_id' => 'required|integer',
            'workflow_id' => 'required|integer|exists:validation_approval_workflows,id',
        ]);

        $approvableClass = Relation::getMorphedModel($data['approvable_type']);
        $approvable = $approvableClass::findOrFail($data['approvable_id']);
        $workflow = ApprovalWorkflow::findOrFail($data['workflow_id']);

        $approvalRequest = $this->service->createApprovalRequest($approvable, $workflow, auth()->user());

        return response()->json($approvalRequest, 201);
    }

    public function show(ApprovalRequest $approval_request)
    {
        return $approval_request->load(['workflow', 'requester', 'actions.approver', 'history']);
    }

    public function approve(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('approve', $approval_request);

        $validated = $request->validate([
            'comment' => 'nullable|string',
        ]);

        $this->service->approveRequest($approval_request, auth()->user(), $validated['comment'] ?? null);

        return $approval_request->refresh();
    }

    public function reject(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('reject', $approval_request);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $this->service->rejectRequest($approval_request, auth()->user(), $validated['reason']);

        return $approval_request->refresh();
    }

    public function delegate(Request $request, ApprovalRequest $approval_request)
    {
        $this->authorize('delegate', $approval_request);

        $validated = $request->validate([
            'to_user_id' => 'required|integer|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $this->service->delegateApproval(
            $approval_request,
            auth()->user(),
            User::findOrFail($validated['to_user_id']),
            $validated['reason'] ?? null
        );

        return $approval_request->refresh();
    }

    public function history(ApprovalRequest $approval_request)
    {
        return $approval_request->history()->get();
    }
}
