<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 8.3: several inventory_* tables were left as bare scaffold stubs
 * (id/tenant_id/status/data/timestamps) despite their models/controllers
 * already declaring real fields — every write below crashed with a
 * QueryException before the 2026_08_27_000001 patch migration. Locks in
 * the fix on the routed, active write paths.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function inventoryStubColumnsTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('creating a cycle count with assigned_to does not crash', function () {
    $user = inventoryStubColumnsTestUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/cycle-counts', [
        'warehouse_id' => $warehouse->id,
        'product_ids' => [$product->id],
        'assigned_to' => $user->id,
    ]);

    $response->assertCreated();
    expect($response->json('assigned_to'))->toBe($user->id);
});

test('creating a picking order with a source_id does not crash', function () {
    $user = inventoryStubColumnsTestUser();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $location = \Modules\Inventory\Models\Location::factory()->create(['warehouse_id' => $warehouse->id]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/picking-orders', [
        'warehouse_id' => $warehouse->id,
        'type' => 'pick',
        'source_type' => 'manual',
        'source_id' => 42,
        'lines' => [[
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_requested' => 5,
        ]],
    ]);

    $response->assertCreated();
    expect($response->json('source_id'))->toBe(42);
});

test('creating a purchase order with expected_at does not crash', function () {
    $user = inventoryStubColumnsTestUser();
    $supplier = Supplier::factory()->create();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/purchase-orders', [
        'supplier_id' => $supplier->id,
        'expected_at' => now()->addDays(7)->toDateString(),
        'items' => [[
            'product_name' => 'Widget',
            'quantity_ordered' => 10,
            'unit_price' => 5,
        ]],
    ]);

    $response->assertCreated();
});

test('receiving a purchase order sets received_at without crashing', function () {
    $user = inventoryStubColumnsTestUser();
    $supplier = Supplier::factory()->create();

    $create = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/purchase-orders', [
        'supplier_id' => $supplier->id,
        'items' => [[
            'product_name' => 'Widget',
            'quantity_ordered' => 10,
            'unit_price' => 5,
        ]],
    ]);
    $poId = $create->json('id');
    $itemId = $create->json('items.0.id');

    test()->actingAs($user, 'sanctum')->postJson("/api/v1/inventory/purchase-orders/{$poId}/send")->assertOk();

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/inventory/purchase-orders/{$poId}/receive", [
        'items' => [
            ['id' => $itemId, 'qty' => 10],
        ],
    ]);

    $response->assertOk();
    expect($response->json('received_at'))->not->toBeNull();
});
