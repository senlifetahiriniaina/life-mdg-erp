<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;

/**
 * Application service for invoice lifecycle operations.
 */
class InvoiceService
{
    /**
     * Create a new invoice (defaults to draft). Invoice numbers must be unique.
     *
     * @throws \Exception when the invoice number already exists
     */
    public function create(array $data): Invoice
    {
        if (! empty($data['number']) && Invoice::where('number', $data['number'])->exists()) {
            throw new \Exception("Invoice number {$data['number']} already exists");
        }

        $data['status'] = $data['status'] ?? 'draft';

        return Invoice::create($data);
    }

    /**
     * Add a line item to an invoice. The line total excludes tax (tax is tracked separately).
     */
    public function addItem(Invoice $invoice, array $data): InvoiceLine
    {
        $quantity = (float) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? 0);
        $taxRate = (float) ($data['tax_rate'] ?? 0);

        $subtotal = $quantity * $unitPrice;
        $taxAmount = $subtotal * $taxRate / 100;

        return InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => $data['description'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal,
        ]);
    }

    /**
     * Calculate the grand total (line subtotals + taxes) for an invoice.
     */
    public function calculateTotal(Invoice $invoice): float
    {
        $lines = InvoiceLine::where('invoice_id', $invoice->id)->get();

        return (float) $lines->sum(fn (InvoiceLine $line) => (float) $line->total + (float) $line->tax_amount);
    }

    public function markAsSent(Invoice $invoice): bool
    {
        $invoice->status = 'sent';
        $invoice->sent_at = now();
        $invoice->save();

        return true;
    }

    /**
     * @throws \Exception when the invoice is still a draft
     */
    public function markAsPaid(Invoice $invoice, $paidAt = null): bool
    {
        if ($invoice->status === 'draft') {
            throw new \Exception('Cannot mark a draft invoice as paid; send it first.');
        }

        $invoice->status = 'paid';
        $invoice->paid_at = $paidAt ?? now();
        $invoice->save();

        return true;
    }

    /**
     * @throws \Exception when the invoice has already been paid
     */
    public function cancel(Invoice $invoice): bool
    {
        if ($invoice->status === 'paid') {
            throw new \Exception('Cannot cancel a paid invoice.');
        }

        $invoice->status = 'cancelled';
        $invoice->save();

        return true;
    }

    /**
     * Generate a unique invoice number of the form INV-{year}-{sequence}.
     */
    public function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $sequence = Invoice::whereYear('created_at', $year)->count() + 1;

        return sprintf('INV-%d-%04d', $year, $sequence);
    }

    /**
     * Delete an invoice and its line items.
     */
    public function delete(Invoice $invoice): bool
    {
        InvoiceLine::where('invoice_id', $invoice->id)->delete();

        return (bool) $invoice->delete();
    }
}
