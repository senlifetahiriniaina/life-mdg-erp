<?php

declare(strict_types=1);

namespace Modules\Achats\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderApproval;
use Modules\Achats\Services\PurchaseApprovalChainService;

class PurchaseApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaseApprovalChainService $approvalService
    ) {}

    /**
     * Get pending approvals for the currently authenticated user.
     *
     * GET /api/achats/approvals/pending
     */
    public function pending(): JsonResponse
    {
        $approvals = $this->approvalService->getPendingApprovalsForUser(auth()->user());

        return response()->json([
            'data' => $approvals,
            'meta' => ['total' => $approvals->count()],
        ]);
    }

    /**
     * Get paginated approval history (all POs), with optional filters.
     *
     * GET /api/achats/approvals/history
     */
    public function history(Request $request): JsonResponse
    {
        $query = PurchaseOrderApproval::with('purchaseOrder.supplier', 'approver');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $history = $query->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'total'    => $history->total(),
                'per_page' => $history->perPage(),
            ],
        ]);
    }

    /**
     * Get global approval statistics.
     *
     * GET /api/achats/approvals/statistics
     */
    public function statistics(): JsonResponse
    {
        $total    = PurchaseOrderApproval::count();
        $pending  = PurchaseOrderApproval::where('status', 'pending')->count();
        $approved = PurchaseOrderApproval::where('status', 'approved')->count();
        $rejected = PurchaseOrderApproval::where('status', 'rejected')->count();

        $poApproved = PurchaseOrder::where('status', 'approved')->count();
        $poRejected = PurchaseOrder::where('status', 'draft')
            ->whereHas('approvals', fn ($q) => $q->where('status', 'rejected'))
            ->count();

        return response()->json([
            'data' => [
                'approval_records' => [
                    'total'    => $total,
                    'pending'  => $pending,
                    'approved' => $approved,
                    'rejected' => $rejected,
                ],
                'purchase_orders' => [
                    'fully_approved' => $poApproved,
                    'rejected'       => $poRejected,
                ],
            ],
        ]);
    }

    /**
     * Initiate the approval workflow for a purchase order.
     *
     * POST /api/achats/purchase-orders/{po}/approval/initiate
     */
    public function initiate(Request $request, PurchaseOrder $po): JsonResponse
    {
        $user = auth()->user();

        if ($po->requested_by !== $user->id && ! $user->hasRole('admin')) {
            return response()->json([
                'error' => 'Only PO creator or admin can initiate approval',
            ], 403);
        }

        if ($po->status !== 'draft') {
            return response()->json([
                'error' => 'PO must be in draft status to initiate approval',
            ], 422);
        }

        try {
            $approverIds = $request->input('approver_ids');
            $this->approvalService->createApprovalChain($po, $approverIds ?? []);

            Log::info('Approval chain initiated', [
                'po_id'        => $po->id,
                'initiated_by' => $user->id,
            ]);

            return response()->json([
                'data'           => $po->refresh(),
                'message'        => 'Approval workflow initiated',
                'approval_chain' => $this->approvalService->getApprovalChain($po),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to initiate approval', [
                'po_id' => $po->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to initiate approval workflow'], 500);
        }
    }

    /**
     * Approve the purchase order at the current pending level.
     *
     * POST /api/achats/purchase-orders/{po}/approval/approve
     */
    public function approve(Request $request, PurchaseOrder $po): JsonResponse
    {
        $user = auth()->user();

        if (! $this->approvalService->canApprove($po, $user)) {
            return response()->json([
                'error' => 'You do not have pending approval authority for this PO',
            ], 403);
        }

        try {
            $this->approvalService->approvePurchaseOrder(
                $po,
                $user,
                $request->input('comments')
            );

            Log::info('PO approved', [
                'po_id'       => $po->id,
                'approved_by' => $user->id,
            ]);

            return response()->json([
                'data'    => $po->refresh(),
                'message' => 'Purchase order approved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve PO', [
                'po_id' => $po->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to approve purchase order'], 500);
        }
    }

    /**
     * Reject the purchase order, cascading to all remaining pending approvals.
     *
     * POST /api/achats/purchase-orders/{po}/approval/reject
     */
    public function reject(Request $request, PurchaseOrder $po): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $user = auth()->user();

        $hasPending = PurchaseOrderApproval::where('purchase_order_id', $po->id)
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if (! $hasPending) {
            return response()->json([
                'error' => 'You do not have pending approval authority for this PO',
            ], 403);
        }

        try {
            $this->approvalService->rejectPurchaseOrder($po, $user, $validated['reason']);

            Log::warning('PO rejected', [
                'po_id'       => $po->id,
                'rejected_by' => $user->id,
                'reason'      => $validated['reason'],
            ]);

            return response()->json([
                'data'    => $po->refresh(),
                'message' => 'Purchase order rejected',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject PO', [
                'po_id' => $po->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to reject purchase order'], 500);
        }
    }

    /**
     * Return the full approval chain for a purchase order.
     *
     * GET /api/achats/purchase-orders/{po}/approval/chain
     */
    public function chain(PurchaseOrder $po): JsonResponse
    {
        return response()->json([
            'data' => $this->approvalService->getApprovalChain($po),
        ]);
    }

    /**
     * Return the approval status summary for a purchase order.
     *
     * GET /api/achats/purchase-orders/{po}/approval/status
     */
    public function approvalStatus(PurchaseOrder $po): JsonResponse
    {
        $stats = $this->approvalService->getApprovalStats($po);

        return response()->json([
            'data' => [
                'po_id'      => $po->id,
                'po_number'  => $po->po_number,
                'status'     => $po->status,
                'statistics' => $stats,
                'chain'      => $this->approvalService->getApprovalChain($po),
            ],
        ]);
    }
}
