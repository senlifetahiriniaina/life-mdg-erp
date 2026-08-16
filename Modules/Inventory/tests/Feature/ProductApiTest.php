<?php

use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;

describe('Product API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    describe('List Products', function () {
        test('can list all products', function () {
            Product::factory()->count(5)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'sku', 'name', 'cost_price', 'sale_price'],
                    ],
                ]);
        });

        test('can filter products by search', function () {
            $product = Product::factory()->create(['name' => 'Unique Product']);
            Product::factory()->count(5)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products?search=Unique');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });

        test('can filter products by category', function () {
            $category = Category::factory()->create();
            Product::factory()->create(['category_id' => $category->id]);
            Product::factory()->count(3)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/inventory/products?category={$category->id}");

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });

        test('can filter products by status', function () {
            Product::factory()->create(['status' => 'active', 'is_active' => true]);
            Product::factory()->count(2)->create(['status' => 'inactive', 'is_active' => false]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products?status=active');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });
    });

    describe('Create Product', function () {
        test('can create a product', function () {
            $data = [
                'sku' => 'SKU001',
                'name' => 'Test Product',
                'cost_price' => 100.00,
                'sale_price' => 150.00,
                'reorder_point' => 5,
                'reorder_qty' => 20,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/products', $data);

            $response->assertStatus(201)
                ->assertJsonPath('data.sku', 'SKU001')
                ->assertJsonPath('data.name', 'Test Product');

            $this->assertDatabaseHas('inventory_products', ['sku' => 'SKU001']);
        });

        test('cannot create product with duplicate SKU', function () {
            Product::factory()->create(['sku' => 'SKU001']);

            $data = [
                'sku' => 'SKU001',
                'name' => 'Duplicate',
                'cost_price' => 100.00,
                'sale_price' => 150.00,
                'reorder_point' => 5,
                'reorder_qty' => 20,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/products', $data);

            $response->assertStatus(422);
        });

        test('validates required fields', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/products', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['sku', 'name', 'selling_price']);
        });
    });

    describe('Read Product', function () {
        test('can get a single product', function () {
            $product = Product::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/inventory/products/{$product->id}");

            $response->assertStatus(200)
                ->assertJsonPath('data.id', $product->id)
                ->assertJsonPath('data.sku', $product->sku);
        });

        test('returns 404 for non-existent product', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products/999999');

            $response->assertStatus(404);
        });
    });

    describe('Update Product', function () {
        test('can update a product', function () {
            $product = Product::factory()->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->patchJson("/api/v1/inventory/products/{$product->id}", [
                    'name' => 'Updated Name',
                    'sale_price' => 200.00,
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.name', 'Updated Name');

            $this->assertDatabaseHas('inventory_products', [
                'id' => $product->id,
                'name' => 'Updated Name',
                'sale_price' => 200.00,
            ]);
        });

        test('can update only specific fields', function () {
            $product = Product::factory()->create(['sale_price' => 100.00]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->patchJson("/api/v1/inventory/products/{$product->id}", [
                    'sale_price' => 250.00,
                ]);

            $response->assertStatus(200);
            $this->assertEquals(250.00, $product->fresh()->sale_price);
        });
    });

    describe('Delete Product', function () {
        test('can delete a product', function () {
            $product = Product::factory()->create(['status' => 'inactive', 'is_active' => false]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->deleteJson("/api/v1/inventory/products/{$product->id}");

            $response->assertStatus(204);
            $this->assertSoftDeleted('inventory_products', ['id' => $product->id]);
        });
    });

    describe('Low Stock Products', function () {
        test('can get low stock products', function () {
            $warehouse = Warehouse::factory()->create();
            $lowProduct = Product::factory()->create(['reorder_point' => 10]);
            $highProduct = Product::factory()->create(['reorder_point' => 10]);

            // Create stock for low product (below reorder point)
            $lowProduct->stock()->create([
                'warehouse_id' => $warehouse->id,
                'quantity' => 2,
            ]);

            // Create stock for high product (above reorder point)
            $highProduct->stock()->create([
                'warehouse_id' => $warehouse->id,
                'quantity' => 50,
            ]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products/low-stock');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });
    });

    describe('Inventory Metrics', function () {
        test('can get inventory metrics', function () {
            Product::factory()->count(5)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products/metrics');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_products',
                    'total_value',
                    'low_stock_count',
                    'total_quantity',
                ]);
        });
    });

    describe('Product Valuation', function () {
        test('can get inventory valuation', function () {
            $warehouse = Warehouse::factory()->create();
            $product = Product::factory()->create([
                'cost_price' => 100.00,
            ]);
            $product->stock()->create([
                'warehouse_id' => $warehouse->id,
                'quantity' => 10,
            ]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/products/valuation');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'by_warehouse');
        });
    });

    describe('Product Properties', function () {
        test('calculates margin percent correctly', function () {
            $product = Product::factory()->create([
                'cost_price' => 100.00,
                'sale_price' => 150.00,
            ]);

            $product->refresh();
            expect($product->getMarginPercent())->toBe(50.0);
        });

        test('detects low stock correctly', function () {
            $warehouse = Warehouse::factory()->create();
            $product = Product::factory()->create([
                'reorder_point' => 10,
            ]);
            $product->stock()->create([
                'warehouse_id' => $warehouse->id,
                'quantity' => 5,
            ]);

            expect($product->isLowStock())->toBeTrue();

            $product->stock()->update(['quantity' => 15]);
            expect($product->fresh()->isLowStock())->toBeFalse();
        });
    });
});
