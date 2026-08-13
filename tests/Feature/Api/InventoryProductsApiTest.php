<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\Product;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/inventory/products')->assertUnauthorized();
    $this->postJson('/api/v1/inventory/products', [])->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated products', function () {
     $user = actingAsUser('employee');
    Product::factory()->count(3)->create();
        $response = $this
        ->getJson('/api/v1/inventory/products')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
});

test('index returns all products', function () {
     $user = actingAsUser('employee');
    Product::factory()->count(4)->create();
        $response = $this
        ->getJson('/api/v1/inventory/products')
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

test('index filters by is_active', function () {
     $user = actingAsUser('employee');
    Product::factory()->count(2)->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);
        $response = $this
        ->getJson('/api/v1/inventory/products?is_active=1')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index searches by name', function () {
     $user = actingAsUser('employee');
    Product::factory()->create(['name' => 'SpecialWidget Pro']);
    Product::factory()->count(3)->create();
        $response = $this
        ->getJson('/api/v1/inventory/products?search=SpecialWidget')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index searches by sku', function () {
     $user = actingAsUser('employee');
    Product::factory()->create(['sku' => 'UNIQUE-SKU-9999']);
    Product::factory()->count(2)->create();
        $response = $this
        ->getJson('/api/v1/inventory/products?search=UNIQUE-SKU-9999')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create a product', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name'       => 'Test Product',
            'sku'        => 'TP-0001',
            'type'       => 'storable',
            'sale_price' => 49.99,
        ])
        ->assertCreated()
        ->assertJsonFragment(['name' => 'Test Product', 'sku' => 'TP-0001']);

    expect(Product::where('sku', 'TP-0001')->exists())->toBeTrue();
});

test('create requires name and sku', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/inventory/products', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'sku']);
});

test('create rejects duplicate sku', function () {
     $user = actingAsUser('employee');
    Product::factory()->create(['sku' => 'DUP-001']);
        $response = $this
        ->postJson('/api/v1/inventory/products', ['name' => 'Other', 'sku' => 'DUP-001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('create rejects invalid type', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name' => 'Bad Type',
            'sku'  => 'BT-001',
            'type' => 'invalid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('create rejects negative price', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->postJson('/api/v1/inventory/products', [
            'name'       => 'Neg Price',
            'sku'        => 'NP-001',
            'sale_price' => -10,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sale_price']);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show a product', function () {
     $user = actingAsUser('employee');
    $product = Product::factory()->create();
        $response = $this
        ->getJson("/api/v1/inventory/products/{$product->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id]);
});

test('show returns 404 for missing product', function () {
     $user = actingAsUser('employee');
        $response = $this
        ->getJson('/api/v1/inventory/products/99999')
        ->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update a product', function () {
     $user = actingAsUser('employee');
    $product = Product::factory()->create(['name' => 'Old Name']);
        $response = $this
        ->putJson("/api/v1/inventory/products/{$product->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    expect($product->fresh()->name)->toBe('New Name');
});

test('can deactivate a product', function () {
     $user = actingAsUser('employee');
    $product = Product::factory()->create(['is_active' => true]);
        $response = $this
        ->putJson("/api/v1/inventory/products/{$product->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonFragment(['is_active' => false]);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete a product', function () {
     $user = actingAsUser('employee');
    $product = Product::factory()->create(['status' => 'inactive', 'is_active' => false]);
        $response = $this
        ->deleteJson("/api/v1/inventory/products/{$product->id}")
        ->assertNoContent();

    expect(Product::find($product->id))->toBeNull();
});

// ── Stock ─────────────────────────────────────────────────────────────────────

test('can fetch product stock levels', function () {
     $user = actingAsUser('employee');
    $product = Product::factory()->create();
        $response = $this
        ->getJson("/api/v1/inventory/products/{$product->id}/stock")
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id]);
});
