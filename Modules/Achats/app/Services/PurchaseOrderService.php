<?php

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Modules\Achats\Events\PurchaseOrderApproved;
use Modules\Achats\Events\PurchaseOrderCancelled;
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
            $this->approvalService->submitApprovalRequest($approvalRequest);

            $approvers = $this->routingService->getApproversForPO($po);
            if ($approvers->isNotEmpty()) {
                $approvalRequest->update(['approver_id' => $approvers->first()->id]);
            }
        }

        event(new PurchaseOrderSubmittedForApproval($po));
    }

    public function markAsApproved(PurchaseOrder $po, User $approver): void
    {
        $po->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        // The PO's own status is the source of truth for the document
        // lifecycle, but until now nothing ever recorded the decision on
        // the ApprovalRequest submitForApproval() creates — it stayed
        // 'pending' forever, so any screen reading real approval
        // steps/decisions (ApprovalPanel) saw the PO as still awaiting a
        // decision even after it was approved. Mirrors the same
        // approvalService->approveRequest() call InvoiceApprovalService
        // already makes.
        $request = \Modules\Validation\Models\ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($request) {
            $this->approvalService->approveRequest($request, $approver);
        }

        event(new PurchaseOrderApproved($po));
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
