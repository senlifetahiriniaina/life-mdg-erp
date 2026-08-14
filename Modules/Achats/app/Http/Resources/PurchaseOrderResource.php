<?php

namespace Modules\Achats\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier?->name,
            'status' => $this->status,
            'order_date' => $this->order_date?->toDateString(),
            'delivery_date' => $this->delivery_date?->toDateString(),
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_cost' => (float) $this->shipping_cost,
            'total' => (float) $this->total,
            'notes' => $this->notes,
            'requested_by' => $this->requested_by,
            'requester_name' => $this->requester?->name,
            'approved_by' => $this->approved_by,
            'approver_name' => $this->approver?->name,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejecter_name' => $this->rejecter?->name,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'lines_count' => $this->lines()->count(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
