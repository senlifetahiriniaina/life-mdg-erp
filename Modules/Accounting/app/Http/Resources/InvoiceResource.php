<?php

namespace Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'journal_id' => $this->journal_id,
            'created_by' => $this->created_by,
            'type' => $this->type,
            'partner_id' => $this->partner_id,
            'partner_name' => $this->partner_name,
            'partner_type' => $this->partner_type,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'amount_paid' => (float) $this->amount_paid,
            'amount_due' => (float) $this->amount_due,
            'status' => $this->status,
            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,
            'notes' => $this->notes,
            'payment_terms' => $this->payment_terms,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Chantier 19 re-verification: never exposed here at all despite show()/index()
            // eager-loading the relation and the real Invoices/Show.vue page reading
            // `invoice.line_items` — the web page happens to work via $invoice->toArray()
            // bypassing this resource entirely, but any real API consumer (or a future web
            // page switched onto this resource) got nothing. Added now that lines are also
            // actually persisted (see InvoiceController::store()/update()).
            'line_items' => $this->whenLoaded('lineItems', fn () => $this->lineItems->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'tax_rate' => (float) $line->tax_rate,
                'subtotal' => (float) $line->subtotal,
                'tax_amount' => (float) $line->tax_amount,
                'total' => (float) $line->total,
            ])),
        ];
    }
}
