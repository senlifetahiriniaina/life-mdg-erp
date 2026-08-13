<?php

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

describe('Stock Movement API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    describe('Record Stock Movement', function () {
        test('can record stock movement in', function () {
            $product = Product::factory()->create();
            $warehouse = Warehouse::factory()->create();
            $product->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 10]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-movements', [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'in',
                    'quantity' => 50,
                    'reason' => 'Purchase receipt',
                ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('inventory_stock_movements', [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 50,
            ]);
        });

        test('can record stock movement out', function () {
            $product = Product::factory()->create();
            $warehouse = Warehouse::factory()->create();
            $product->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 100]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-movements', [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'out',
                    'quantity' => -30,
                    'reason' => 'Sale',
                ]);

            $response->assertStatus(201);
        });

        test('can record stock adjustment', function () {
            $product = Product::factory()->create();
            $warehouse = Warehouse::factory()->create();
            $product->stock()->create(['warehouse_id' => $warehouse->id, 'quantity' => 50]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-movements', [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'type' => 'adjustment',
                    'quantity' => 10,
                    'reason' => 'Physical count adjustment',
                ]);

            $response->assertStatus(201);
        });

        test('validates required fields', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/v1/inventory/stock-movements', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['product_id', 'type', 'quantity']);
        });
    });

    describe('List Stock Movements', function () {
        test('can list all stock movements', function () {
            StockMovement::factory()->count(5)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/stock-movements');

            $response->assertStatus(200)
                ->assertJsonCount(5, 'data');
        });

        test('can filter stock movements by product', function () {
            $product = Product::factory()->create();
            StockMovement::factory()->create(['product_id' => $product->id]);
            StockMovement::factory()->count(3)->create();

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/inventory/stock-movements?product_id={$product->id}");

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });

        test('can filter stock movements by type', function () {
            StockMovement::factory()->create(['type' => 'in']);
            StockMovement::factory()->count(2)->create(['type' => 'out']);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v1/inventory/stock-movements?type=in');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });
    });

    describe('Product Stock History', function () {
        test('can get product stock history', function () {
            $product = Product::factory()->create();
            StockMovement::factory()->count(5)->create(['product_id' => $product->id]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/inventory/products/{$product->id}/history");

            $response->assertStatus(200)
                ->assertJsonCount(5, 'data');
        });

        test('stock history respects limit parameter', function () {
            $product = Product::factory()->create();
            StockMovement::factory()->count(10)->create(['product_id' => $product->id]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson("/api/v1/inventory/products/{$product->id}/history?limit=5");

            $response->assertStatus(200)
                ->assertJsonCount(5, 'data');
        });
    });
});
