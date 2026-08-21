<?php

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Achats\Events\PurchaseOrderApproved;
use Modules\Achats\Events\PurchaseOrderCancelled;
use Modules\Achats\Events\PurchaseOrderRejected;
use Modules\Achats\Events\PurchaseOrderInvoiced;
use Modules\Achats\Events\PurchaseOrderReceived;
use Modules\Achats\Events\PurchaseOrderSubmittedForApproval;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Validation\Services\ApprovalRequestService;

class PurchaseOrderService
{
    protected ApprovalRequestService $approvalService;
    protected ApprovalRoutingService $routingService;

    public function __construct(ApprovalRequestService $approvalService, ApprovalRoutingService $routingService)
    {
        $this->approvalService = $approvalService;
        $this->routingService = $routingService;
    }

    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        $data['po_number'] = $this->generatePONumber();
        $data['order_date'] = $data['order_date'] ?? now()->toDateString();
        $data['currency'] = $data['currency'] ?? config('achats.default_currency', 'USD');
        $data['status'] = $data['status'] ?? 'draft';

        return PurchaseOrder::create($data);
    }

    public function updatePurchaseOrder(PurchaseOrder $po, array $data): PurchaseOrder
    {
        // Cannot update if not in draft status
        if (! $po->isDraft()) {
            throw new \Exception('Cannot update purchase order that is not in draft status');
        }

        $po->update($data);

        return $po;
    }

    public function addLineItem(PurchaseOrder $po, array $lineData): PurchaseOrderLine
    {
        if (! $po->isDraft()) {
            throw new \Exception('Cannot add line items to non-draft purchase order');
        }

        $lineData['line_total'] = $lineData['quantity'] * $lineData['unit_price'];

        return $po->lines()->create($lineData);
    }

    public function removeLine(PurchaseOrderLine $line): bool
    {
        if (! $line->purchaseOrder->isDraft()) {
            throw new \Exception('Cannot remove line from non-draft purchase order');
        }

        return $line->delete();
    }

    public function calculateTotals(PurchaseOrder $po): array
    {
        return $po->calculateTotals();
    }

    public function submitForApproval(PurchaseOrder $po, User $requestedBy): void
    {
        if (! $po->isDraft()) {
            throw new \Exception('Only draft purchase orders can be submitted');
        }

        // Update PO status
        $po->update([
            'status' => 'submitted',
            'requested_by' => $requestedBy->id,
        ]);

        // Create approval request — routed through the workflow whose rules
        // actually match this PO's amount (getApplicableWorkflow), not just
        // whichever Achats workflow happened to be created first.
        $workflow = $this->routingService->getApplicableWorkflow($po);

        if ($workflow) {
            $approvalRequest = $this->approvalService->createApprovalRequest($po, $workflow, $requestedBy);

            // Chantier 32.13: resolve and set approver_id BEFORE calling
            // submitApprovalRequest() (not after, as before) — confirmed
            // empirically via tinker that submitApprovalRequest() fires
            // Modules\Validation\Events\ApprovalRequestCreated synchronously,
            // and Modules\Validation\Listeners\NotifyApprovalParticipants::
            // handleRequestCreated() reads $event->request->approver_id at
            // that exact moment to decide who to notify — with the old
            // ordering, approver_id was still NULL when the event fired
            // (only ever set on a second, later update() call), so the
            // resolved approver was silently never notified of a single new
            // Achats approval request, ever. getApproversForPO() re-queries
            // the request by (approvable, workflow) rather than taking it as
            // a parameter, so it already finds the just-created row
            // regardless of this reordering — safe, no behavior change
            // beyond the notification timing.
            $approvers = $this->routingService->getApproversForPO($po);
            if ($approvers->isNotEmpty()) {
                $approvalRequest->update(['approver_id' => $approvers->first()->id]);
            }

            $this->approvalService->submitApprovalRequest($approvalRequest->fresh());
        }

        event(new PurchaseOrderSubmittedForApproval($po));
    }

    /**
     * The PO's own status is the source of truth for the document lifecycle,
     * but it must not flip to 'approved' until the linked ApprovalRequest
     * actually finishes — POs >= 50K XOF route through 3 real approver
     * levels (ApprovalRoutingService::createDefaultWorkflows()) now that
     * ApprovalRequestService::approveRequest() advances current_level
     * instead of finalizing on the first decision. Finalizing the PO here
     * regardless of level would silently skip levels 2/3 one layer up from
     * where that bug used to live. Requests with no request at all (no
     * submitForApproval() call) or a single-level workflow still finalize
     * on this first call, matching the previous behavior exactly.
     */
    public function markAsApproved(PurchaseOrder $po, User $approver): void
    {
        // Chantier 32.13 (layer 8, business validation): confirmed
        // empirically via tinker that this method had NO guard on the PO's
        // own current status at all — a still-draft PO that had never been
        // submitted could be force-approved directly (bypassing the entire
        // multi-tier escalation chain this module's approval seeder exists
        // for), and — more severely — an already-terminal PO (received,
        // invoiced, cancelled) could be silently "re-approved", flipping
        // its status backwards with zero relation to its real, already-
        // completed PurchaseReceipt. 'draft' is deliberately still allowed
        // here (not just 'submitted') to preserve the pre-existing, still-
        // intentionally-tested escape hatch (ApprovalRoutingIntegrationTest
        // ::test_marking_a_po_approved_without_a_pending_request_does_not_fail)
        // for a caller that marks a PO approved directly, without going
        // through submitForApproval() first — but a PO already resolved one
        // way or another (approved/rejected/cancelled/received/invoiced)
        // can never be transitioned again through this method.
        if (! in_array($po->status, ['draft', 'submitted'], true)) {
            throw new \RuntimeException("Cannot approve a purchase order with status '{$po->status}'.");
        }

        $request = \Modules\Validation\Models\ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($request) {
            $this->approvalService->approveRequest($request, $approver);

            if ($request->fresh()->status !== 'approved') {
                return;
            }
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        event(new PurchaseOrderApproved($po));
    }

    /**
     * Mirrors markAsApproved(): PurchaseOrder never had a reject() at all
     * (only submit/approve/cancel), which forced ApprovalPanel into
     * read-only mode on PurchaseOrders/Show.vue.
     */
    public function markAsRejected(PurchaseOrder $po, User $rejector, string $reason): void
    {
        // Chantier 32.13: same status guard as markAsApproved() above —
        // confirmed empirically that an already-'cancelled' PO could be
        // silently "rejected", flipping it away from its real terminal
        // state. 'draft'/'submitted' are both legitimate: a manager may
        // reasonably reject a bad draft outright, not only a submitted one.
        if (! in_array($po->status, ['draft', 'submitted'], true)) {
            throw new \RuntimeException("Cannot reject a purchase order with status '{$po->status}'.");
        }

        $po->update([
            'status' => 'rejected',
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
        ]);

        $request = \Modules\Validation\Models\ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($request) {
            $this->approvalService->rejectRequest($request, $rejector, $reason);
        }

        event(new PurchaseOrderRejected($po, $reason));
    }

    public function markAsReceived(PurchaseOrder $po, array $receiptData)
    {
        $receiptData['receipt_number'] = $this->generateReceiptNumber($po);
        $receiptData['receipt_date'] = $receiptData['receipt_date'] ?? now()->toDateString();
        $receiptData['purchase_order_id'] = $po->id;

        $receipt = $po->receipt()->create($receiptData);

        $po->update(['status' => 'received']);

        event(new PurchaseOrderReceived($po, $receipt));

        return $receipt;
    }

    public function markAsInvoiced(PurchaseOrder $po, int $invoiceId): void
    {
        $po->update(['status' => 'invoiced']);

        $po->accountingMapping()->update([
            'invoice_id' => $invoiceId,
            'status' => 'posted',
        ]);

        event(new PurchaseOrderInvoiced($po));
    }

    public function cancelPurchaseOrder(PurchaseOrder $po, string $reason): void
    {
        $po->update(['status' => 'cancelled']);

        event(new PurchaseOrderCancelled($po, $reason));
    }

    public function getPOsByStatus(string $status): Collection
    {
        return PurchaseOrder::where('status', $status)->get();
    }

    public function getPOsBySupplier($supplier): Collection
    {
        $supplierId = is_object($supplier) ? $supplier->id : $supplier;

        return PurchaseOrder::where('supplier_id', $supplierId)->get();
    }

    public function getPOsByDateRange(string $from, string $to): Collection
    {
        return PurchaseOrder::whereBetween('order_date', [$from, $to])->get();
    }

    public function generatePONumber(): string
    {
        $format = config('achats.po_number_format', 'PO-{YYYY}-{MM}-{SEQUENCE}');
        $sequence = PurchaseOrder::count() + 1;

        return str_replace(
            ['{YYYY}', '{MM}', '{SEQUENCE}'],
            [now()->year, now()->format('m'), str_pad($sequence, 5, '0', STR_PAD_LEFT)],
            $format
        );
    }

    public function generateReceiptNumber(PurchaseOrder $po): string
    {
        $receiptCount = $po->receipt()->count() + 1;

        return "RCP-{$po->po_number}-".str_pad($receiptCount, 3, '0', STR_PAD_LEFT);
    }

    public function getPOsForApproval(): Collection
    {
        return PurchaseOrder::where('status', 'submitted')->get();
    }

    public function getPOsOverduedForReceipt(): Collection
    {
        return PurchaseOrder::where('status', 'approved')
            ->where('delivery_date', '<', now()->toDateString())
            ->get();
    }
}
