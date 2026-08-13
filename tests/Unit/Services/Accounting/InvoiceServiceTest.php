<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Services\InvoiceService;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService();
    }

    public function test_can_create_invoice_with_valid_data(): void
    {
        $invoice = $this->service->create([
            'number' => 'INV-001',
            'customer_name' => 'ACME Corp',
            'customer_email' => 'contact@acme.com',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
        ]);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('INV-001', $invoice->number);
        $this->assertEquals('ACME Corp', $invoice->customer_name);
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_cannot_create_invoice_with_duplicate_number(): void
    {
        Invoice::factory()->create(['number' => 'INV-001']);

        $this->expectException(\Exception::class);
        $this->service->create([
            'number' => 'INV-001',
            'customer_name' => 'Duplicate',
            'customer_email' => 'dup@example.com',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
        ]);
    }

    public function test_can_add_line_item_to_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $item = $this->service->addItem($invoice, [
            'description' => 'Consulting services',
            'quantity' => 10,
            'unit_price' => 100.00,
            'tax_rate' => 10,
        ]);

        $this->assertInstanceOf(InvoiceLine::class, $item);
        $this->assertEquals(10, $item->quantity);
        $this->assertEquals(100.00, $item->unit_price);
        $this->assertEquals(1000.00, $item->total);
    }

    public function test_invoice_total_includes_all_items(): void
    {
        $invoice = Invoice::factory()->create();

        $this->service->addItem($invoice, [
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_price' => 100.00,
            'tax_rate' => 0,
        ]);

        $this->service->addItem($invoice, [
            'description' => 'Item 2',
            'quantity' => 3,
            'unit_price' => 50.00,
            'tax_rate' => 0,
        ]);

        $total = $this->service->calculateTotal($invoice);

        $this->assertEquals(350.00, $total);
    }

    public function test_invoice_total_includes_tax(): void
    {
        $invoice = Invoice::factory()->create();

        $this->service->addItem($invoice, [
            'description' => 'Taxable item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
        ]);

        $total = $this->service->calculateTotal($invoice);

        $this->assertEquals(110.00, $total);
    }

    public function test_can_mark_invoice_as_sent(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'draft']);

        $result = $this->service->markAsSent($invoice);

        $this->assertTrue($result);
        $this->assertEquals('sent', $invoice->refresh()->status);
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_can_mark_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'sent']);

        $result = $this->service->markAsPaid($invoice, now());

        $this->assertTrue($result);
        $this->assertEquals('paid', $invoice->refresh()->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_cannot_mark_draft_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'draft']);

        $this->expectException(\Exception::class);
        $this->service->markAsPaid($invoice, now());
    }

    public function test_can_cancel_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'draft']);

        $result = $this->service->cancel($invoice);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $invoice->refresh()->status);
    }

    public function test_cannot_cancel_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'paid']);

        $this->expectException(\Exception::class);
        $this->service->cancel($invoice);
    }

    public function test_can_generate_invoice_number(): void
    {
        $number = $this->service->generateInvoiceNumber();

        $this->assertStringStartsWith('INV-', $number);
        $this->assertStringContainsString((string) now()->year, $number);
    }

    public function test_invoice_items_are_deleted_on_invoice_deletion(): void
    {
        $invoice = Invoice::factory()->create();
        $this->service->addItem($invoice, [
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
        ]);

        $invoiceId = $invoice->id;
        $itemsCount = InvoiceLine::where('invoice_id', $invoiceId)->count();

        $this->service->delete($invoice);

        $this->assertEquals(0, InvoiceLine::where('invoice_id', $invoiceId)->count());
    }
}
