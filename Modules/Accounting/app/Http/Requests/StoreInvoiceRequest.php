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
            // Chantier 19 re-verification: was 'required_without:journal_id', but the real,
            // routed Invoices/Form.vue (customer-style: customer_id + lines, no journal_id and
            // no total — it relies on the server computing total from lines, which
            // InvoiceController::store() already does) never sends `total` at all. Every real
            // invoice creation through the UI 422'd on this rule before store()'s own
            // line-total computation ever ran — confirmed empirically, not a hypothetical gap.
            // Now only required when neither journal_id nor lines are present.
            'total' => 'required_without_all:journal_id,lines|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'amount_due' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'required_without_all:customer_id,total|array',
            'lines.*' => 'array',
            'line_items' => 'nullable|array',
            'line_items.*' => 'array',
        ];
    }
}
