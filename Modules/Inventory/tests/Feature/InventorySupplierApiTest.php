<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Warehouse;


beforeEach(function () {
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $this->user = User::factory()->create();
    $this->user->assignRole('employee');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('authenticated user can list suppliers', function () {
    Supplier::factory()->count(3)->create();

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/suppliers')
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('authenticated user can create a supplier', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/suppliers', [
            'name' => 'Acme Corp',
            'email' => 'supply@acme.com',
            'currency' => 'USD',
            'payment_terms' => 'net30',
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Acme Corp');
});

it('authenticated user can create a purchase order', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/inventory/purchase-orders', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'currency' => 'USD',
            'shipping_cost' => 0,
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity_ordered' => 10,
                'unit_price' => 15.00,
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'draft');
});

it('authenticated user can send a purchase order', function () {
    $po = PurchaseOrder::factory()->create(['status' => 'draft']);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/purchase-orders/{$po->id}/send")
        ->assertOk()
        ->assertJsonPath('status', 'sent');
});

it('unauthenticated user cannot access suppliers', function () {
    $this->getJson('/api/v1/inventory/suppliers')->assertUnauthorized();
});
