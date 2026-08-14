<?php

namespace Modules\Validation\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalRequestController extends Controller
{
    public function index()
    {
        return Inertia::render('Validation/ApprovalRequests/Index');
    }

    public function show(ApprovalRequest $approval_request)
    {
        $approval_request->load(['workflow.rules', 'hierarchy.levels', 'requester', 'approver', 'actions.approver', 'history.changedBy']);

        return Inertia::render('Validation/ApprovalRequests/Show', [
            'approvalRequest' => array_merge($approval_request->toArray(), [
                'can_approve' => auth()->user()?->can('approve', $approval_request) ?? false,
                'can_reject' => auth()->user()?->can('reject', $approval_request) ?? false,
            ]),
        ]);
    }
}
