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
     */
    public function index(Invoice $invoice): JsonResponse
    {
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
     */
    public function approve(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        $approvalRequest = $this->service->approve($invoice, $request->user(), $request->input('comment'));

        return response()->json($approvalRequest);
    }

    /**
     * POST /api/v1/accounting/invoices/{invoice}/reject
     */
    public function reject(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

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
