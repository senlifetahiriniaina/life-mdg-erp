<?php

declare(strict_types=1);

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\InvoiceApproval;
use Modules\Accounting\Models\PaymentSchedule;
use Modules\Accounting\Services\InvoiceApprovalService;

class InvoiceApprovalController extends Controller
{
    public function __construct(private readonly InvoiceApprovalService $service) {}

    /**
     * GET /api/v1/accounting/approvals
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;
        $status   = $request->query('status');

        $query = InvoiceApproval::with('steps')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * POST /api/v1/accounting/approvals
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id'   => 'required|integer',
            'amount'       => 'required|numeric|min:0',
            'invoice_type' => 'nullable|in:supplier,customer,expense',
            'currency'     => 'nullable|string|size:3',
        ]);

        $tenantId = $request->user()?->tenant_id ?? 1;
        $userId   = $request->user()?->id ?? 0;

        $approval = $this->service->submitForApproval(
            (int) $request->input('invoice_id'),
            $userId,
            array_merge($request->all(), ['tenant_id' => $tenantId]),
        );

        return response()->json($approval, 201);
    }

    /**
     * GET /api/v1/accounting/approvals/{id}
     */
    public function show(int $id): JsonResponse
    {
        $approval = InvoiceApproval::with('steps')->findOrFail($id);
        return response()->json($approval);
    }

    /**
     * POST /api/v1/accounting/approvals/{id}/process
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action'  => 'required|in:approved,rejected',
            'comment' => 'nullable|string|max:1000',
        ]);

        $userId   = $request->user()?->id ?? 0;
        $approval = $this->service->processApproval(
            $id,
            $userId,
            $request->input('action'),
            $request->input('comment', ''),
        );

        return response()->json($approval);
    }

    /**
     * GET /api/v1/accounting/approvals/pending
     * My pending approvals.
     */
    public function pending(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;
        $userId   = $request->user()?->id ?? 0;

        $approvals = $this->service->getPendingApprovals($userId, $tenantId);
        return response()->json($approvals);
    }

    /**
     * GET /api/v1/accounting/approvals/{invoiceId}/history
     */
    public function history(int $invoiceId): JsonResponse
    {
        return response()->json($this->service->getApprovalHistory($invoiceId));
    }

    // ─── Payment Schedules ─────────────────────────────────────────────

    /**
     * GET /api/v1/accounting/payment-schedules
     */
    public function paymentSchedules(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;
        $month    = $request->query('month', now()->format('Y-m'));

        $schedules = PaymentSchedule::where('tenant_id', $tenantId)
            ->whereYear('due_date', substr($month, 0, 4))
            ->whereMonth('due_date', substr($month, 5, 2))
            ->orderBy('due_date')
            ->get();

        return response()->json($schedules);
    }

    /**
     * POST /api/v1/accounting/payment-schedules/check-overdue
     * Cron-friendly endpoint: marks overdue scheduled payments.
     */
    public function checkOverdue(Request $request): JsonResponse
    {
        $tenantId = $request->user()?->tenant_id ?? 1;
        $count    = $this->service->checkOverduePayments($tenantId);

        return response()->json(['marked_overdue' => $count]);
    }
}
