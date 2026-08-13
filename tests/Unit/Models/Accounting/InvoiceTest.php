<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\InvoicePayment;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_has_many_items(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->count(3)->create(['invoice_id' => $invoice->id]);

        $this->assertEquals(3, $invoice->lineItems()->count());
    }

    public function test_invoice_number_is_required(): void
    {
        $this->expectException(\Exception::class);
        Invoice::create([
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'invoice_date' => now()->toDateString(),
        ]);
    }

    public function test_invoice_number_must_be_unique(): void
    {
        Invoice::factory()->create(['number' => 'INV-001']);

        $this->expectException(\Exception::class);
        Invoice::create([
            'number' => 'INV-001',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
        ]);
    }

    public function test_invoice_status_defaults_to_draft(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertEquals('draft', $invoice->status);
    }

    public function test_invoice_scope_unpaid_returns_non_paid_invoices(): void
    {
        Invoice::factory()->count(2)->create(['status' => 'paid']);
        Invoice::factory()->count(3)->create(['status' => 'sent']);

        $unpaid = Invoice::unpaid()->count();

        $this->assertEquals(3, $unpaid);
    }

    public function test_invoice_scope_overdue_returns_past_due_invoices(): void
    {
        Invoice::factory()->create([
            'due_date' => now()->subDays(1)->toDateString(),
            'status' => 'sent',
        ]);

        Invoice::factory()->create([
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'sent',
        ]);

        $overdue = Invoice::overdue()->count();

        $this->assertGreaterThan(0, $overdue);
    }

    public function test_invoice_can_have_payments(): void
    {
        $invoice = Invoice::factory()->create();
        InvoicePayment::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->assertEquals(2, $invoice->payments()->count());
    }

    public function test_invoice_can_calculate_total_amount(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Item 1',
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'description' => 'Item 2',
            'quantity' => 1,
            'unit_price' => 50,
            'total' => 50,
        ]);

        $total = $invoice->lineItems->sum('total');

        $this->assertEquals(150, $total);
    }

    public function test_invoice_tracks_dates(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertNotNull($invoice->invoice_date);
        $this->assertNotNull($invoice->due_date);
    }

    public function test_invoice_stores_customer_information(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_name' => 'ACME Corp',
            'customer_email' => 'acme@example.com',
            'customer_phone' => '555-1234',
            'customer_address' => '123 Main St',
        ]);

        $this->assertEquals('ACME Corp', $invoice->customer_name);
        $this->assertEquals('acme@example.com', $invoice->customer_email);
    }
}
