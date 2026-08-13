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
        return Inertia::render('Validation/ApprovalRequests/Show', [
            'approvalRequest' => $approval_request->load(['workflow', 'requester', 'actions.approver', 'history']),
        ]);
    }
}
