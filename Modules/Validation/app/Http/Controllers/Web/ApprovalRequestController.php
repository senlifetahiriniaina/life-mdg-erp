<?php

namespace Modules\Validation\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalRequestController extends Controller
{
    public function index()
    {
        return Inertia::render('Validation/ApprovalRequests/Index');
    }

    /**
     * Chantier 19 Lot 3: the JSON API's show()/history() are gated by
     * ApprovalRequestPolicy::view() (Chantier 8.5sv), but this Inertia web
     * route had zero authorize() call at all — any authenticated user could
     * view any other user's/company's approval request detail (requester,
     * approver, comments, full history) just by visiting the URL, an IDOR
     * independent of the already-fixed JSON path.
     */
    public function show(ApprovalRequest $approval_request)
    {
        $this->authorize('view', $approval_request);

        $approval_request->load(['workflow.rules', 'hierarchy.levels', 'requester', 'approver', 'actions.approver', 'history.changedBy']);

        return Inertia::render('Validation/ApprovalRequests/Show', [
            'approvalRequest' => array_merge($approval_request->toArray(), [
                'can_approve' => auth()->user()?->can('approve', $approval_request) ?? false,
                'can_reject' => auth()->user()?->can('reject', $approval_request) ?? false,
            ]),
        ]);
    }
}
