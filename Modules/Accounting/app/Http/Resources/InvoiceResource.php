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
        ];
    }
}
