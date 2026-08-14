<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Achats\Services\ApprovalRoutingService;
use Modules\Achats\Services\PurchaseOrderService;
use Modules\Validation\Models\ApprovalRequest;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Exercises the real, live integration point (PurchaseOrderService::submitForApproval())
 * that used to grab whichever Achats workflow existed first, ignoring PO amount
 * entirely. Bypasses SupplierFactory/PurchaseOrderFactory (pre-existing, unrelated
 * to this change: generic fake() data doesn't match real column types/schema —
 * confirmed failing identically on unmodified code) by building test rows directly
 * against each model's real $fillable.
 */
class ApprovalRoutingIntegrationTest extends TestCase
{
    protected function makeSupplier(): Supplier
    {
        return Supplier::create([
            'code' => 'SUP-TEST',
            'name' => 'Test Supplier',
            'email' => 'supplier@example.test',
            'is_active' => true,
        ]);
    }

    protected function makePurchaseOrder(float $total, Supplier $supplier): PurchaseOrder
    {
        return PurchaseOrder::create([
            'po_number' => 'PO-TEST-'.$total,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => $total,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'total' => $total,
        ]);
    }

    public function test_submit_for_approval_routes_small_po_to_tier_one()
    {
        app(ApprovalRoutingService::class)->createDefaultWorkflows();

        Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
        $purchasingManager = User::factory()->create();
        $purchasingManager->assignRole('purchasing-manager');

        $supplier = $this->makeSupplier();
        $po = $this->makePurchaseOrder(2000, $supplier);
        $requester = User::factory()->create();

        app(PurchaseOrderService::class)->submitForApproval($po, $requester);

        $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->first();

        $this->assertNotNull($request, 'submitForApproval should create a Validation approval request');
        $this->assertEquals('pending', $request->status);
        $this->assertEquals($purchasingManager->id, $request->approver_id);
    }

    public function test_submit_for_approval_routes_large_po_to_higher_tier()
    {
        app(ApprovalRoutingService::class)->createDefaultWorkflows();

        Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $purchasingManager = User::factory()->create();
        $purchasingManager->assignRole('purchasing-manager');

        $supplier = $this->makeSupplier();
        // >= 50000 matches the 3-tier "Standard PO Approval" rule, escalating
        // through purchasing-manager -> manager -> admin (3 levels).
        $po = $this->makePurchaseOrder(75000, $supplier);
        $requester = User::factory()->create();

        app(PurchaseOrderService::class)->submitForApproval($po, $requester);

        $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->first();

        $this->assertNotNull($request);
        // Level 1's only holder (purchasing-manager) is assigned — same
        // resolution the small-PO test exercises; this test's real point is
        // that the >=50000 rule (not the <5000 one) is what got matched.
        $this->assertEquals($purchasingManager->id, $request->approver_id);
        $this->assertEquals(3, $request->total_levels);
    }

    public function test_marking_a_po_approved_also_approves_its_linked_approval_request()
    {
        app(ApprovalRoutingService::class)->createDefaultWorkflows();

        Role::firstOrCreate(['name' => 'purchasing-manager', 'guard_name' => 'web']);
        $purchasingManager = User::factory()->create();
        $purchasingManager->assignRole('purchasing-manager');

        $supplier = $this->makeSupplier();
        $po = $this->makePurchaseOrder(2000, $supplier);
        $requester = User::factory()->create();

        $service = app(PurchaseOrderService::class);
        $service->submitForApproval($po, $requester);

        $request = ApprovalRequest::where('approvable_type', PurchaseOrder::class)
            ->where('approvable_id', $po->id)
            ->first();
        $this->assertEquals('pending', $request->status);

        $service->markAsApproved($po, $purchasingManager);

        $this->assertEquals('approved', $po->fresh()->status);
        $this->assertEquals('approved', $request->fresh()->status);
        $this->assertDatabaseHas('validation_approval_actions', [
            'request_id' => $request->id,
            'approver_id' => $purchasingManager->id,
            'action' => 'approved',
        ]);
    }

    public function test_marking_a_po_approved_without_a_pending_request_does_not_fail()
    {
        $supplier = $this->makeSupplier();
        $po = $this->makePurchaseOrder(2000, $supplier);
        $approver = User::factory()->create();

        // No submitForApproval() call — no ApprovalRequest exists at all.
        app(PurchaseOrderService::class)->markAsApproved($po, $approver);

        $this->assertEquals('approved', $po->fresh()->status);
    }
}
