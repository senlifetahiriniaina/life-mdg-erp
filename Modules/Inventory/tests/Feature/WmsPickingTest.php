<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\PickingLine;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list picking orders', function () {
    $warehouse = Warehouse::factory()->create();
    PickingOrder::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/picking-orders')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('can create a picking order', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $location = Location::factory()->create(['warehouse_id' => $warehouse->id]);

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/picking-orders', [
            'warehouse_id' => $warehouse->id,
            'type' => 'pick',
            'lines' => [[
                'product_id' => $product->id,
                'location_id' => $location->id,
                'quantity_requested' => 5,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('type', 'pick');
});

it('can assign a picker to a picking order', function () {
    $warehouse = Warehouse::factory()->create();
    $po = PickingOrder::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'pending']);
    $picker = User::factory()->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/picking-orders/{$po->id}/assign", ['user_id' => $picker->id])
        ->assertOk()
        ->assertJsonPath('assigned_to', $picker->id);
});

it('can record a pick for a line', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $location = Location::factory()->create(['warehouse_id' => $warehouse->id]);
    $po = PickingOrder::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'in_progress']);
    $line = PickingLine::factory()->create([
        'picking_order_id' => $po->id,
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity_requested' => 10,
        'quantity_picked' => 0,
        'status' => 'pending',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/picking-orders/{$po->id}/lines/{$line->id}/pick", ['quantity' => 5])
        ->assertOk()
        ->assertJsonPath('status', 'partial');
});

it('can complete a picking order', function () {
    $warehouse = Warehouse::factory()->create();
    $po = PickingOrder::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'in_progress']);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/picking-orders/{$po->id}/complete")
        ->assertOk()
        ->assertJsonPath('status', 'completed');
});

it('can get next pick line', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $location = Location::factory()->create(['warehouse_id' => $warehouse->id]);
    $po = PickingOrder::factory()->create(['warehouse_id' => $warehouse->id]);
    PickingLine::factory()->create([
        'picking_order_id' => $po->id,
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity_requested' => 10,
        'quantity_picked' => 0,
        'status' => 'pending',
    ]);

    $this->withToken($this->token)
        ->getJson("/api/v1/inventory/picking/{$po->id}/next-pick")
        ->assertOk()
        ->assertJsonStructure(['id', 'product', 'location']);
});

it('unauthenticated user cannot access picking orders', function () {
    $this->getJson('/api/v1/inventory/picking-orders')->assertUnauthorized();
});
