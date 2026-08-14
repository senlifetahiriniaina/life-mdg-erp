<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\PurchaseOrderService;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    protected PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchaseOrderService::class);
    }

    public function test_can_create_purchase_order()
    {
        $supplier = Supplier::factory()->create();
        $user = User::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(10)->toDateString(),
            'currency' => 'USD',
            'created_by' => $user->id,
        ];

        $po = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertTrue($po->isDraft());
        $this->assertStringContainsString('PO-', $po->po_number);
    }

    public function test_purchase_order_number_is_unique()
    {
        $supplier = Supplier::factory()->create();

        $po1 = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);

        $po2 = $this->service->createPurchaseOrder([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'created_by' => User::factory()->create()->id,
        ]);

        $this->assertNotEquals($po1->po_number, $po2->po_number);
    }

    public function test_can_add_line_item_to_draft_po()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $lineData = [
            'description' => 'Test Product',
            'quantity' => 10,
            'unit' => 'pcs',
            'unit_price' => 100,
            'tax_rate' => 10,
        ];

        $line = $this->service->addLineItem($po, $lineData);

        $this->assertEquals('Test Product', $line->description);
        $this->assertEquals(1000, $line->line_total);
    }

    public function test_cannot_add_line_to_non_draft_po()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'approved']);

        $this->expectException(\Exception::class);

        $this->service->addLineItem($po, ['description' => 'Test']);
    }

    public function test_can_submit_po_for_approval()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);
        $user = User::factory()->create();

        $this->service->submitForApproval($po, $user);

        $po->refresh();
        $this->assertEquals('submitted', $po->status);
        $this->assertEquals($user->id, $po->requested_by);
    }

    public function test_can_approve_purchase_order()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'submitted']);
        $approver = User::factory()->create();

        $this->service->markAsApproved($po, $approver);

        $po->refresh();
        $this->assertEquals('approved', $po->status);
        $this->assertEquals($approver->id, $po->approved_by);
        $this->assertNotNull($po->approved_at);
    }

    public function test_can_reject_purchase_order()
    {
        // Neither PurchaseOrderFactory (fake()->word() for approved_at/etc —
        // see test_can_approve_purchase_order, which fails on this exact
        // same pre-existing bug) nor SupplierFactory (inserts an 'address'
        // column achats_suppliers doesn't have — see test_can_create_purchase_order)
        // are usable here; build the PO via the service with a plain integer
        // supplier_id (the column carries no FK constraint) to isolate this
        // test from those unrelated, already-failing gaps.
        $po = $this->service->createPurchaseOrder([
            'supplier_id' => 1,
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'created_by' => User::factory()->create()->id,
        ]);
        $po->update(['status' => 'submitted']);
        $rejector = User::factory()->create();

        $this->service->markAsRejected($po, $rejector, 'Budget insuffisant');

        $po->refresh();
        $this->assertEquals('rejected', $po->status);
        $this->assertEquals($rejector->id, $po->rejected_by);
        $this->assertNotNull($po->rejected_at);
    }

    public function test_can_mark_po_as_received()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'approved']);

        $receiptData = [
            'received_by' => User::factory()->create()->id,
            'warehouse_location' => 'Zone A',
        ];

        $receipt = $this->service->markAsReceived($po, $receiptData);

        $this->assertNotNull($receipt);
        $po->refresh();
        $this->assertEquals('received', $po->status);
    }

    public function test_can_cancel_purchase_order()
    {
        $po = PurchaseOrder::factory()->create(['status' => 'draft']);

        $this->service->cancelPurchaseOrder($po, 'Budget cut');

        $po->refresh();
        $this->assertEquals('cancelled', $po->status);
    }

    public function test_calculate_po_totals()
    {
        $po = PurchaseOrder::factory()->create();
        $po->lines()->createMany([
            [
                'description' => 'Item 1',
                'quantity' => 10,
                'unit_price' => 100,
                'line_total' => 1000,
                'tax_rate' => 10,
            ],
            [
                'description' => 'Item 2',
                'quantity' => 5,
                'unit_price' => 200,
                'line_total' => 1000,
                'tax_rate' => 10,
            ],
        ]);

        $totals = $this->service->calculateTotals($po);

        $this->assertEquals(2000, $totals['subtotal']);
        $this->assertEquals(200, $totals['tax_amount']);
    }

    public function test_get_pos_by_status()
    {
        PurchaseOrder::factory()->count(3)->create(['status' => 'draft']);
        PurchaseOrder::factory()->count(2)->create(['status' => 'approved']);

        $drafts = $this->service->getPOsByStatus('draft');

        $this->assertEquals(3, $drafts->count());
    }

    public function test_get_pos_by_supplier()
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->count(3)->create(['supplier_id' => $supplier->id]);

        $pos = $this->service->getPOsBySupplier($supplier);

        $this->assertEquals(3, $pos->count());
    }
}
