<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;
use Modules\Inventory\Models\CostingSheet;
use Modules\Inventory\Models\ProductionOrder;
use Modules\Sales\Models\SalesOrder;

uses(RefreshDatabase::class);

// Chantier 24 (volet D de la feuille de route Chantier 21) — traçabilité
// bout-en-bout : agrège en lecture seule fiche de chiffrage, commande
// client (avec acompte/solde), achats matières liés, et sous-traitance,
// sur la vraie route HTTP.

function productionOrderTraceUser(): User
{
    return actingAsUser('purchasing-manager');
}

test('the traceability web page renders the real Inertia component', function () {
    productionOrderTraceUser();
    $order = ProductionOrder::factory()->create();

    $this->get("/inventory/production-orders/{$order->id}/trace")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory/ProductionOrders/Trace', false)
            ->where('productionOrderId', $order->id)
        );
});

test('the trace endpoint aggregates costing sheet, sales order, subcontractor, and linked purchase orders', function () {
    $user = productionOrderTraceUser();

    $sheet = CostingSheet::factory()->create(['name' => 'Pantalon EPI', 'total_cost_price' => 10000, 'suggested_selling_price' => 15000]);
    $supplier = Supplier::factory()->create(['name' => 'Sous-traitant Antananarivo']);
    $salesOrder = SalesOrder::create([
        'tenant_id' => 1, 'reference' => 'SO-TRACE-TEST', 'status' => 'confirmed',
        'currency' => 'MGA', 'subtotal' => 500000, 'discount_amount' => 0, 'tax_amount' => 0,
        'total' => 500000, 'created_by' => $user->id,
    ]);

    $order = ProductionOrder::factory()->create([
        'costing_sheet_id' => $sheet->id,
        'subcontractor_supplier_id' => $supplier->id,
        'sales_order_id' => $salesOrder->id,
    ]);

    PurchaseOrder::create([
        'po_number' => 'PO-TRACE-TEST', 'supplier_id' => $supplier->id, 'status' => 'approved',
        'order_date' => now(), 'currency' => 'MGA', 'subtotal' => 200000, 'tax_amount' => 0,
        'shipping_cost' => 0, 'total' => 200000, 'production_order_id' => $order->id,
    ]);

    // A second, unrelated PO must not leak into this order's trace.
    PurchaseOrder::create([
        'po_number' => 'PO-UNRELATED', 'supplier_id' => $supplier->id, 'status' => 'approved',
        'order_date' => now(), 'currency' => 'MGA', 'subtotal' => 50000, 'tax_amount' => 0,
        'shipping_cost' => 0, 'total' => 50000,
    ]);

    $response = $this->getJson("/api/v1/inventory/production-orders/{$order->id}/trace")->assertOk();

    expect($response->json('data.costing_sheet.name'))->toBe('Pantalon EPI');
    expect($response->json('data.sales_order.reference'))->toBe('SO-TRACE-TEST');
    expect($response->json('data.subcontractor.name'))->toBe('Sous-traitant Antananarivo');
    expect($response->json('data.material_purchase_orders'))->toHaveCount(1);
    expect($response->json('data.material_purchase_orders.0.po_number'))->toBe('PO-TRACE-TEST');
});

test('a production order with no links returns null sections rather than erroring', function () {
    productionOrderTraceUser();
    $order = ProductionOrder::factory()->create();

    $response = $this->getJson("/api/v1/inventory/production-orders/{$order->id}/trace")->assertOk();

    expect($response->json('data.costing_sheet'))->toBeNull();
    expect($response->json('data.sales_order'))->toBeNull();
    expect($response->json('data.subcontractor'))->toBeNull();
    expect($response->json('data.material_purchase_orders'))->toBe([]);
});

test('a sales-rep (no inventory access) cannot reach the trace endpoint', function () {
    actingAsUser('sales-rep');
    $order = ProductionOrder::factory()->create();

    $this->getJson("/api/v1/inventory/production-orders/{$order->id}/trace")->assertForbidden();
});
