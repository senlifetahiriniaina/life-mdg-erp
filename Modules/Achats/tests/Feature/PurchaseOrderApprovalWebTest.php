<?php

namespace Modules\Achats\Tests\Feature;

use App\Models\User;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Tests\TestCase;

/**
 * PurchaseOrders/Show.vue already had a working web route and real Inertia
 * props (purchaseOrder), but the page ignored them and did its own
 * fetch()+meta-token call instead — a meta tag that was never added to any
 * Blade layout, so `document.querySelector('meta[name="api-token"]').content`
 * would throw before the request was ever sent.
 */
class PurchaseOrderApprovalWebTest extends TestCase
{
    public function test_show_renders_with_real_inertia_props()
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'code' => 'SUP-WEB-TEST',
            'name' => 'Web Test Supplier',
            'email' => 'web-test-supplier@example.test',
            'is_active' => true,
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-WEB-TEST',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'total' => 1000,
        ]);

        $response = $this->actingAs($user)->get("/purchase-orders/{$po->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Achats/PurchaseOrders/Show', false)
            ->where('purchaseOrder.id', $po->id)
            ->has('purchaseOrder.can_approve')
        );
    }
}
