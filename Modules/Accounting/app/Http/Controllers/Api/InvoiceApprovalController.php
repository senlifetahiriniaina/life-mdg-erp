<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Services\InvoiceApprovalService;

class InvoiceApprovalController extends Controller
{
    public function __construct(private readonly InvoiceApprovalService $service) {}

    /**
     * GET /api/v1/accounting/invoices/{invoice}/approvals
     * Approval chain (action log) for the invoice.
     *
     * Chantier 31: authorizes against the real underlying ApprovalRequest
     * (Modules\Validation\Policies\ApprovalRequestPolicy::view — requester,
     * assigned approver, or admin/manager) when one exists, matching
     * Validation's own ApprovalRequestController::show() precedent. Before
     * this fix, any user holding the outer route-gate role could read any
     * invoice's full approval chain (approver names, comments) regardless
     * of any relationship to that specific request.
     */
    public function index(Invoice $invoice): JsonResponse
    {
        $approvalRequest = $this->service->findLatestRequest($invoice);

        if ($approvalRequest) {
            $this->authorize('view', $approvalRequest);
        }

        return response()->json([
            'level' => $this->service->getApprovalLevel((float) $invoice->total),
            'label' => $this->service->getLevelLabel($this->service->getApprovalLevel((float) $invoice->total)),
            'chain' => $this->service->getApprovalChain($invoice),
        ]);
    }

    /**
     * POST /api/v1/accounting/invoices/{invoice}/approvals
     * Submit the invoice for approval.
     */
    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $approvalRequest = $this->service->submitForApproval($invoice, $request->user());

        return response()->json($approvalRequest, 201);
    }

    /**
     * POST /api/v1/accounting/invoices/{invoice}/approve
     *
     * Chantier 31 fix: confirmed empirically that any user holding the
     * outer route-gate role (accountant/finance-manager/manager/admin) —
     * even one not assigned as this specific request's approver, or
     * holding a role that doesn't match the level's required_role, or
     * acting on an already-decided (non-pending) request — could approve
     * it. Fixed by resolving the real ApprovalRequest first and
     * authorizing against it via the already-correct, already Gate-
     * registered ApprovalRequestPolicy::approve() (checks approver_id ===
     * user->id, or admin/manager, and status === 'pending'), matching the
     * exact pattern Validation's own ApprovalRequestController::approve()
     * already uses for the same policy/model.
     */
    public function approve(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        $approvalRequest = $this->service->findLatestRequest($invoice);
        abort_if(! $approvalRequest, 404, 'No approval request found for this invoice.');
        $this->authorize('approve', $approvalRequest);

        $approvalRequest = $this->service->approve($invoice, $request->user(), $request->input('comment'));

        return response()->json($approvalRequest);
    }

    /**
     * POST /api/v1/accounting/invoices/{invoice}/reject
     *
     * Chantier 31 fix: same gap and fix as approve() above, mirrored onto
     * ApprovalRequestPolicy::reject().
     */
    public function reject(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $approvalRequest = $this->service->findLatestRequest($invoice);
        abort_if(! $approvalRequest, 404, 'No approval request found for this invoice.');
        $this->authorize('reject', $approvalRequest);

        $approvalRequest = $this->service->reject($invoice, $request->user(), $request->input('reason'));

        return response()->json($approvalRequest);
    }

    /**
     * GET /api/v1/accounting/invoices/approvals/pending
     * My pending invoice approvals.
     */
    public function pending(Request $request): JsonResponse
    {
        return response()->json($this->service->getPendingForUser($request->user())->values());
    }
}
