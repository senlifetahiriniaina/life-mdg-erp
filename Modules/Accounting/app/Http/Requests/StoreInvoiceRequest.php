<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Accounting\Models\Invoice;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        return [
            'number' => 'nullable|string|unique:acc_invoices,number',
            // Two invoice styles: journal-style (journal_id + lines) or customer-style
            // (customer_id + due_date + total). Require the fields of whichever style is used.
            'journal_id' => 'required_without:customer_id|integer|exists:acc_journals,id',
            'type' => 'nullable|string|in:invoice,bill,credit_note,debit_note,sales,purchase',
            'customer_id' => 'required_without:journal_id|integer',
            'partner_id' => 'nullable|integer',
            'partner_name' => 'nullable|string',
            'partner_type' => 'nullable|string|in:customer,vendor',
            'invoice_date' => 'required|date',
            'due_date' => 'required_without:journal_id|date',
            'status' => 'nullable|string|in:draft,sent,paid,overdue,cancelled',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total' => 'required_without:journal_id|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_due' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'required_without:customer_id|array',
            'lines.*' => 'array',
            'line_items' => 'nullable|array',
            'line_items.*' => 'array',
        ];
    }
}
