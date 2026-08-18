<?php

namespace Modules\Achats\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chantier 10: built alongside wiring PurchaseReceiptController's real
 * logic. Aliases PurchaseReceiptLine's real column names
 * (variance_qty/purchaseOrderLine) to the field names
 * PurchaseReceipts/{Index,Show}.vue actually read (variance/po_line) —
 * a pure serialization-shape translation, not new business logic.
 */
class PurchaseReceiptResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $lines = $this->whenLoaded('lines');
        $hasQualityIssues = $lines instanceof \Illuminate\Support\Collection
            ? $lines->contains(fn ($l) => ! in_array($l->quality_status, ['good', 'acceptable']))
            : false;
        $qualityIssuesCount = $lines instanceof \Illuminate\Support\Collection
            ? $lines->filter(fn ($l) => ! in_array($l->quality_status, ['good', 'acceptable']))->count()
            : 0;

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'supplier' => $this->purchaseOrder->relationLoaded('supplier') ? [
                    'id' => $this->purchaseOrder->supplier?->id,
                    'name' => $this->purchaseOrder->supplier?->name,
                    'email' => $this->purchaseOrder->supplier?->email,
                    'phone' => $this->purchaseOrder->supplier?->phone,
                ] : null,
            ]),
            'receipt_date' => $this->receipt_date?->toDateString(),
            'received_by' => $this->received_by,
            'warehouse_location' => $this->warehouse_location,
            'notes' => $this->notes,
            'status' => $this->status,
            'lines_count' => $lines instanceof \Illuminate\Support\Collection ? $lines->count() : $this->lines()->count(),
            'has_quality_issues' => $hasQualityIssues,
            'quality_issues_count' => $qualityIssuesCount,
            'lines' => $lines instanceof \Illuminate\Support\Collection
                ? $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'purchase_order_line_id' => $line->purchase_order_line_id,
                    'product_id' => $line->product_id,
                    'quantity_received' => (float) $line->quantity_received,
                    'quality_status' => $line->quality_status,
                    'variance' => (float) $line->variance_qty,
                    'notes' => $line->notes,
                    'po_line' => $line->relationLoaded('purchaseOrderLine') && $line->purchaseOrderLine ? [
                        'id' => $line->purchaseOrderLine->id,
                        'description' => $line->purchaseOrderLine->description,
                        'quantity' => (float) $line->purchaseOrderLine->quantity,
                    ] : null,
                ])
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
