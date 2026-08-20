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
            // Chantier 10: PurchaseOrders/Form.vue's edit mode does
            // Object.assign(form.value, data) against this resource's
            // response, expecting a real `lines` array to pre-fill the
            // line-items table — this key never existed at all (only the
            // count did), so editing a PO always started from a blank line
            // list. Combined with PurchaseOrderController::update()'s new
            // delete-and-recreate-lines behavior, omitting this would have
            // made every edit silently wipe the PO's real lines instead of
            // just failing to show them.
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'line_total' => (float) $line->line_total,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Chantier 22 (volet B — cycle acompte/solde).
            'deposit_percent' => $this->deposit_percent !== null ? (float) $this->deposit_percent : null,
            'deposit_required_amount' => $this->deposit_required_amount !== null ? (float) $this->deposit_required_amount : null,
            'deposit_invoice_id' => $this->deposit_invoice_id,
            'balance_invoice_id' => $this->balance_invoice_id,
            'payment_stage' => $this->payment_stage,
        ];
    }
}
