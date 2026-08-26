<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;

uses(RefreshDatabase::class);

// ── Auth & Authorization ──────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/inventory/products')->assertUnauthorized();
    $this->postJson('/api/v1/inventory/products', [])->assertUnauthorized();
});

test('employee can view products', function () {
    $user = actingAsUser('employee');
    Product::factory()->create();
    $this->getJson('/api/v1/inventory/products')->assertOk();
});

test('manager can create products', function () {
    $user = actingAsUser('manager');
    $this->postJson('/api/v1/inventory/products', [
        'name'           => 'Test Product',
        'sku'            => 'TEST-001',
        'cost_price'     => 100,
        'selling_price'  => 150,
    ])->assertCreated();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated products', function () {
    $user = actingAsUser('employee');
    Product::factory()->count(25)->create();
    $response = $this
        ->getJson('/api/v1/inventory/products')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    expect($response->json('total'))->toBe(25);
});

test('index filters by status', function () {
    $user = actingAsUser('employee');
    Product::factory()->count(5)->create(['status' => 'active']);
    Product::factory()->count(3)->create(['status' => 'inactive']);

    $response = $this
        ->getJson('/api/v1/inventory/products?status=active')
        ->assertOk();
    expect($response->json('total'))->toBe(5);
});

test('index filters by category', function () {
    $user = actingAsUser('employee');
    Product::factory()->count(4)->create(['category' => 'Electronics']);
    Product::factory()->count(3)->create(['category' => 'Software']);

    $response = $this
        ->getJson('/api/v1/inventory/products?category=Electronics')
        ->assertOk();
    expect($response->json('total'))->toBe(4);
});

test('index searches by name and sku', function () {
    $user = actingAsUser('employee');
    Product::factory()->create(['name' => 'Laptop Pro', 'sku' => 'LAP-001']);
    Product::factory()->create(['name' => 'Mouse', 'sku' => 'MOU-001']);

    $response = $this->getJson('/api/v1/inventory/products?search=LAP-001')->assertOk();
    expect($response->json('total'))->toBe(1);

    $response = $this->getJson('/api/v1/inventory/products?search=Laptop')->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('index sorts by cost_price', function () {
    $user = actingAsUser('employee');
    Product::factory()->create(['name' => 'Cheap', 'cost_price' => 10]);
    Product::factory()->create(['name' => 'Expensive', 'cost_price' => 1000]);

    $response = $this
        ->getJson('/api/v1/inventory/products?sort=-cost_price')
        ->assertOk();
    expect($response->json('data')[0]['name'])->toBe('Expensive');
});

test('index filters by price range', function () {
    $user = actingAsUser('employee');
    Product::factory()->create(['selling_price' => 50]);
    Product::factory()->create(['selling_price' => 200]);
    Product::factory()->create(['selling_price' => 5000]);

    $response = $this
        ->getJson('/api/v1/inventory/products?price_min=100&price_max=1000')
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create product with required fields', function () {
    $user = actingAsUser('manager');
    $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name'          => 'New Widget',
            'sku'           => 'WID-001',
            'cost_price'    => 50,
            'selling_price' => 100,
        ])
        ->assertCreated();
    expect(Product::where('name', 'New Widget')->exists())->toBeTrue();
});

test('can create product with all fields', function () {
    $user = actingAsUser('manager');
    $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name'           => 'Complete Product',
            'sku'            => 'COMP-001',
            'category'       => 'Electronics',
            'description'    => 'A detailed product description',
            'cost_price'     => 100,
            'selling_price'  => 250,
            'unit'           => 'pieces',
            'reorder_level'  => 20,
            'status'         => 'active',
        ])
        ->assertCreated()
        ->assertJsonFragment(['category' => 'Electronics']);
});

test('create requires name, sku, cost_price, selling_price', function () {
    $user = actingAsUser('manager');
    $this->postJson('/api/v1/inventory/products', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'sku', 'selling_price']);
});

test('create validates sku is unique', function () {
    $user = actingAsUser('manager');
    Product::factory()->create(['sku' => 'UNIQUE-001']);
    $this->postJson('/api/v1/inventory/products', [
        'name'          => 'Another Product',
        'sku'           => 'UNIQUE-001',
        'cost_price'    => 50,
        'selling_price' => 100,
    ])->assertUnprocessable()->assertJsonValidationErrors(['sku']);
});

test('create validates selling_price >= cost_price', function () {
    $user = actingAsUser('manager');
    $this->postJson('/api/v1/inventory/products', [
        'name'          => 'Bad Price',
        'sku'           => 'BAD-001',
        'cost_price'    => 100,
        'selling_price' => 50, // Less than cost
    ])->assertUnprocessable();
});

test('created product belongs to tenant', function () {
    $user = actingAsUser('manager');
    $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name'          => 'Test Product',
            'sku'           => 'TST-001',
            'cost_price'    => 50,
            'selling_price' => 100,
        ])
        ->assertCreated();
    $product = Product::find($response->json('data.id'));
    expect($product->tenant_id)->toBe($user->tenant_id);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show product with warehouse stock', function () {
    $user = actingAsUser('employee');
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    Stock::factory()->create([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    $response = $this
        ->getJson("/api/v1/inventory/products/{$product->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id]);
});

test('show returns 404 for missing product', function () {
    $user = actingAsUser('employee');
    $this->getJson('/api/v1/inventory/products/99999')->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update product fields', function () {
    $user = actingAsUser('manager');
    $product = Product::factory()->create(['name' => 'Old Name']);
    $this
        ->putJson("/api/v1/inventory/products/{$product->id}", ['name' => 'New Name'])
        ->assertOk();
    expect($product->fresh()->name)->toBe('New Name');
});

test('can update prices', function () {
    $user = actingAsUser('manager');
    $product = Product::factory()->create(['cost_price' => 100, 'selling_price' => 150]);
    $this
        ->putJson("/api/v1/inventory/products/{$product->id}", [
            'cost_price'    => 120,
            'selling_price' => 200,
        ])
        ->assertOk();
    $fresh = $product->fresh();
    expect((int) $fresh->cost_price)->toBe(120);
    expect((int) $fresh->selling_price)->toBe(200);
});

test('update validates selling_price >= cost_price', function () {
    $user = actingAsUser('manager');
    $product = Product::factory()->create();
    $this->putJson("/api/v1/inventory/products/{$product->id}", [
        'cost_price'    => 100,
        'selling_price' => 50,
    ])->assertUnprocessable();
});

test('cannot update sku to duplicate', function () {
    $user = actingAsUser('manager');
    $product1 = Product::factory()->create(['sku' => 'SKU-001']);
    $product2 = Product::factory()->create(['sku' => 'SKU-002']);

    $this->putJson("/api/v1/inventory/products/{$product2->id}", ['sku' => 'SKU-001'])
        ->assertUnprocessable();
});

// ── Stock Management ──────────────────────────────────────────────────────────

test('can adjust stock level', function () {
    $user = actingAsUser('manager');
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    Stock::factory()->create([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity'     => 100,
    ]);

    $this->patchJson("/api/v1/inventory/products/{$product->id}/stock/{$warehouse->id}", [
        'quantity' => 150,
        'reason'   => 'inbound',
    ])->assertOk();

    $stock = Stock::where('product_id', $product->id)->first();
    expect((int) $stock->quantity)->toBe(150);
});

test('validates low stock alerts', function () {
    $user = actingAsUser('manager');
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['reorder_level' => 20]);
    Stock::factory()->create([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity'     => 50,
    ]);

    $response = $this->patchJson("/api/v1/inventory/products/{$product->id}/stock/{$warehouse->id}", [
        'quantity' => 10,
        'reason'   => 'sale',
    ])->assertOk();

    // Should include low stock warning
    expect($response->json('low_stock_alert'))->toBeTrue();
});

test('can transfer stock between warehouses', function () {
    $user = actingAsUser('manager');
    $warehouse1 = Warehouse::factory()->create();
    $warehouse2 = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Stock::factory()->create([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse1->id,
        'quantity'     => 100,
    ]);
    Stock::factory()->create([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse2->id,
        'quantity'     => 50,
    ]);

    $this->postJson("/api/v1/inventory/products/{$product->id}/transfer", [
        'from_warehouse_id' => $warehouse1->id,
        'to_warehouse_id'   => $warehouse2->id,
        'quantity'          => 30,
    ])->assertOk();

    expect((int) Stock::where([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse1->id,
    ])->first()->quantity)->toBe(70);

    expect((int) Stock::where([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse2->id,
    ])->first()->quantity)->toBe(80);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete inactive product', function () {
    $user = actingAsUser('manager');
    $product = Product::factory()->create(['status' => 'inactive']);
    $this->deleteJson("/api/v1/inventory/products/{$product->id}")->assertNoContent();
    expect(Product::find($product->id))->toBeNull();
});

test('cannot delete active product', function () {
    $user = actingAsUser('manager');
    $product = Product::factory()->create(['status' => 'active']);
    $this->deleteJson("/api/v1/inventory/products/{$product->id}")
        ->assertForbidden();
});

// ── Reporting ─────────────────────────────────────────────────────────────────

test('can get inventory valuation report', function () {
    $user = actingAsUser('employee');
    $product = Product::factory()->create(['cost_price' => 100]);
    $warehouse = Warehouse::factory()->create();
    Stock::factory()->create([
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity'     => 50,
    ]);

    $response = $this
        ->getJson('/api/v1/inventory/valuation')
        ->assertOk();
    expect($response->json())->toHaveKeys(['total_value', 'by_warehouse']);
});

test('can get low stock report', function () {
    $user = actingAsUser('employee');
    $product1 = Product::factory()->create(['reorder_level' => 50]);
    $product2 = Product::factory()->create(['reorder_level' => 20]);
    $warehouse = Warehouse::factory()->create();

    Stock::factory()->create([
        'product_id'   => $product1->id,
        'warehouse_id' => $warehouse->id,
        'quantity'     => 30, // Below reorder level
    ]);
    Stock::factory()->create([
        'product_id'   => $product2->id,
        'warehouse_id' => $warehouse->id,
        'quantity'     => 100, // Above reorder level
    ]);

    $response = $this
        ->getJson('/api/v1/inventory/low-stock')
        ->assertOk();
    expect($response->json())->toHaveKey('low_stock_items');
    expect(count($response->json('low_stock_items')))->toBe(1);
});
