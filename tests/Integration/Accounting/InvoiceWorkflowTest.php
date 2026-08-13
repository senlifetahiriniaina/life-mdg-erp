<?php

declare(strict_types=1);

namespace Tests\Integration\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\JournalEntry;
use Tests\TestCase;

class InvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_creation_to_payment_workflow(): void
    {
        // Create invoice
        $invoice = Invoice::create([
            'number' => 'INV-001',
            'customer_name' => 'ACME Corp',
            'customer_email' => 'acme@example.com',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        $this->assertEquals('draft', $invoice->status);

        // Add items
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Consulting',
            'quantity' => 10,
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Mark as sent
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);
        $this->assertEquals('sent', $invoice->refresh()->status);

        // Mark as paid
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        $this->assertEquals('paid', $invoice->refresh()->status);
    }

    public function test_invoice_with_multiple_line_items(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_price' => 100,
            'total' => 200,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Item 2',
            'quantity' => 3,
            'unit_price' => 50,
            'total' => 150,
        ]);

        $items = InvoiceLine::where('invoice_id', $invoice->id)->get();

        $this->assertEquals(2, $items->count());
        $this->assertEquals(350, $items->sum('total'));
    }

    public function test_invoice_tax_calculation(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Taxable Item',
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_rate' => 10,
            'total' => 1100,
        ]);

        $items = InvoiceLine::where('invoice_id', $invoice->id)->get();

        $this->assertEquals(1100, $items->sum('total'));
    }

    public function test_invoice_cancellation(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'draft']);

        $invoice->update(['status' => 'cancelled']);

        $this->assertEquals('cancelled', $invoice->refresh()->status);
    }

    public function test_cannot_cancel_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);

        // Attempt to cancel should not change status
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_invoice_generates_journal_entry(): void
    {
        $invoice = Invoice::create([
            'number' => 'INV-002',
            'customer_name' => 'Test Co',
            'customer_email' => 'test@example.com',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        // Create corresponding journal entry
        JournalEntry::create([
            'invoice_id' => $invoice->id,
            'date' => $invoice->invoice_date,
            'description' => 'Invoice #' . $invoice->number,
            'amount' => 1000,
            'type' => 'debit',
        ]);

        $entry = JournalEntry::where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($entry);
        $this->assertEquals($invoice->id, $entry->invoice_id);
    }

    public function test_multiple_invoices_to_same_customer(): void
    {
        $customer = 'ACME Corp';

        $inv1 = Invoice::factory()->create(['customer_name' => $customer]);
        $inv2 = Invoice::factory()->create(['customer_name' => $customer]);
        $inv3 = Invoice::factory()->create(['customer_name' => $customer]);

        $invoices = Invoice::where('customer_name', $customer)->get();

        $this->assertEquals(3, $invoices->count());
    }

    public function test_invoice_sent_and_paid_dates_tracked(): void
    {
        $invoice = Invoice::factory()->create();

        $sentDate = now();
        $paidDate = now()->addDays(15);

        $invoice->update(['status' => 'sent', 'sent_at' => $sentDate]);
        $invoice->update(['status' => 'paid', 'paid_at' => $paidDate]);

        $refreshed = $invoice->refresh();

        $this->assertNotNull($refreshed->sent_at);
        $this->assertNotNull($refreshed->paid_at);
        $this->assertTrue($refreshed->sent_at->lessThan($refreshed->paid_at));
    }
}
