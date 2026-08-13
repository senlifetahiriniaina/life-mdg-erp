<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Invoice;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        return [
            'number' => 'nullable|string|unique:acc_invoices,number,'.$this->invoice->id,
            'type' => 'nullable|string|in:invoice,bill,credit_note,debit_note',
            'partner_id' => 'nullable|integer',
            'partner_name' => 'nullable|string',
            'partner_type' => 'nullable|string|in:customer,vendor',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date|after:invoice_date',
            'status' => 'nullable|in:draft,sent,paid,overdue,cancelled',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_due' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
        ];
    }
}
