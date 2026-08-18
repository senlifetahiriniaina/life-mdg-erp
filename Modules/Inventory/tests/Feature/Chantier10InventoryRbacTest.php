<?php

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 10 re-verification pass: Inventory had zero registerPolicies()
 * call anywhere and zero authorize() calls in any controller despite
 * WarehousePolicy/StockMovementPolicy/StockPolicy all being fully written —
 * the same "policy exists, never registered, never called" bug class found
 * repeatedly elsewhere this session (Core/BI/HR/Calendar/Strategy/API/CRM).
 * Also locks in the StockMovementPolicy permission-string fix
 * (inventory.stockmovement.* -> inventory.stock-movement.*, which never
 * matched what RolesAndPermissionsSeeder actually seeds) and the
 * previously-dead StockMovementController::show() route.
 */
describe('Chantier 10 Inventory RBAC', function () {
    test('warehouse-operator cannot create a warehouse (view-only by design)', function () {
        $user = actingAsUser('warehouse-operator');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/warehouses', [
                'name' => 'Depot X',
                'type' => 'main',
                'address' => '1 rue X',
                'city' => 'Antananarivo',
                'country' => 'MG',
                'is_active' => true,
            ]);

        $response->assertStatus(403);
    });

    test('warehouse-operator can view a warehouse', function () {
        $user = actingAsUser('warehouse-operator');
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/inventory/warehouses/{$warehouse->id}");

        $response->assertStatus(200);
    });

    test('admin can view a single stock movement (previously-dead show route)', function () {
        $user = actingAsUser('admin');
        $movement = StockMovement::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/inventory/stock-movements/{$movement->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $movement->id);
    });

    test('warehouse-operator can create a stock movement against the real seeded permission', function () {
        $user = actingAsUser('warehouse-operator');
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 10]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/inventory/stock-movements', [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'in',
                'quantity' => 5,
            ]);

        $response->assertStatus(201);
    });
});
