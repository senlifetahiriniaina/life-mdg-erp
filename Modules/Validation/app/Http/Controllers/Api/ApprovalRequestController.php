<?php

namespace Modules\Validation\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\Validation\Models\ApprovalRequest;
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
        // Manual creation of approval requests - typically triggered by events
        $data = $request->validate([
            'approvable_type' => 'required|string',
            'approvable_id' => 'required|integer',
        ]);

        // Implementation to follow
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

        // Implementation to follow
    }

    public function history(ApprovalRequest $approval_request)
    {
        return $approval_request->history()->get();
    }
}
