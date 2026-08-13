<?php

namespace Modules\Achats\Services;

use Modules\Achats\Events\PurchaseReceiptCompleted;
use Modules\Achats\Events\QualityIssueRecorded;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;
use Modules\Achats\Models\PurchaseReceiptLine;

class PurchaseReceiptService
{
    protected PurchaseOrderService $poService;

    public function __construct(PurchaseOrderService $poService)
    {
        $this->poService = $poService;
    }

    public function createReceipt(PurchaseOrder $po, array $data): PurchaseReceipt
    {
        if ($po->receipt()->exists()) {
            throw new \Exception('Purchase order already has a receipt');
        }

        return $this->poService->markAsReceived($po, $data);
    }

    public function addReceiptLine(PurchaseReceipt $receipt, array $lineData): PurchaseReceiptLine
    {
        if ($receipt->status !== 'draft') {
            throw new \Exception('Cannot add lines to completed receipt');
        }

        return $receipt->lines()->create($lineData);
    }

    public function markLineAsReceived(
        PurchaseReceiptLine $line,
        float $qty,
        string $qualityStatus = 'good'
    ): void {
        $poLine = $line->purchaseOrderLine;
        $variance = $poLine->quantity - $qty;

        $line->update([
            'quantity_received' => $qty,
            'quality_status' => $qualityStatus,
            'variance_qty' => $variance,
        ]);

        // Update PO line received quantity
        $poLine->update([
            'received_qty' => $qty,
            'line_status' => $qty == $poLine->quantity ? 'received' : 'partial',
        ]);
    }

    public function recordQualityIssue(
        PurchaseReceiptLine $line,
        string $issueType,
        string $description
    ): void {
        $line->update([
            'quality_status' => $issueType,
            'notes' => $description,
        ]);

        event(new QualityIssueRecorded($line->receipt, $line, $issueType, $description));
    }

    public function completeReceipt(PurchaseReceipt $receipt): void
    {
        if ($receipt->lines()->where('quality_status', 'good')->doesntExist()) {
            throw new \Exception('Receipt must have at least one good line item');
        }

        $receipt->markReceived();

        // Sync to inventory
        event(new PurchaseReceiptCompleted($receipt));
    }

    public function getReceiptVariances(PurchaseReceipt $receipt): array
    {
        $lines = $receipt->lines()->get();

        $quantityVariances = [];
        $valueVariances = [];

        foreach ($lines as $line) {
            $poLine = $line->purchaseOrderLine;
            $variance = (float) $poLine->quantity - (float) $line->quantity_received;

            if ($variance !== 0.0) {
                $quantityVariances[] = [
                    'line_id' => $line->id,
                    'description' => $poLine->description,
                    'expected_qty' => $poLine->quantity,
                    'received_qty' => $line->quantity_received,
                    'variance_qty' => $variance,
                    'variance_value' => $variance * (float) $poLine->unit_price,
                ];
            }

            if ($line->quality_status !== 'good') {
                $valueVariances[] = [
                    'line_id' => $line->id,
                    'quality_status' => $line->quality_status,
                    'amount' => (float) $line->quantity_received * (float) $poLine->unit_price,
                ];
            }
        }

        return [
            'quantity_variances' => $quantityVariances,
            'quality_variances' => $valueVariances,
            'total_variance_value' => array_sum(array_column($quantityVariances, 'variance_value')),
        ];
    }

    public function getReceivingReport(PurchaseOrder $po): array
    {
        $receipt = $po->receipt;

        if (! $receipt) {
            return [];
        }

        $variances = $this->getReceiptVariances($receipt);

        return [
            'po_number' => $po->po_number,
            'supplier_name' => $po->supplier->name,
            'receipt_date' => $receipt->receipt_date,
            'received_by' => $receipt->receivedBy->name,
            'warehouse_location' => $receipt->warehouse_location,
            'lines' => $receipt->lines()->with('purchaseOrderLine')->get()->map(function ($line) {
                return [
                    'po_line_id' => $line->purchaseOrderLine->id,
                    'description' => $line->purchaseOrderLine->description,
                    'expected_qty' => $line->purchaseOrderLine->quantity,
                    'received_qty' => $line->quantity_received,
                    'quality_status' => $line->quality_status,
                    'unit_price' => $line->purchaseOrderLine->unit_price,
                    'total_value' => (float) $line->quantity_received * (float) $line->purchaseOrderLine->unit_price,
                ];
            }),
            'variances' => $variances,
            'total_received_value' => $receipt->total_received_value,
        ];
    }
}
