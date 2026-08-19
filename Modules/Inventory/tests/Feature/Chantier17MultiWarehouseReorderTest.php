<?php

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ReorderRule;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 17c — a multi-warehouse maturity assessment found that no code
 * path anywhere in this module actually read `ReorderRule.warehouse_id`
 * overrides: `ProductController::lowStockReport()` (the endpoint the real,
 * live ReorderAutomation/Index.vue page calls) always compared every
 * warehouse's stock row against the same global `Product.reorder_level`,
 * so two warehouses with different sales velocity for the same product
 * could never have different reorder points in practice — despite the
 * schema already supporting it. This locks in the fix.
 */
beforeEach(function () {
    $this->user = actingAsUser('admin');
});

describe('lowStockReport() honors per-warehouse ReorderRule overrides', function () {
    test('a warehouse with a real ReorderRule uses its min_level instead of the product global default', function () {
        $product = Product::factory()->create(['reorder_level' => 100]);
        $warehouseA = Warehouse::factory()->create();
        $warehouseB = Warehouse::factory()->create();

        // Same quantity (30) in both warehouses — below the global 100
        // reorder_level in both, but warehouse A has its own, much lower
        // ReorderRule (min_level 10), so it should NOT be flagged.
        Stock::create(['product_id' => $product->id, 'warehouse_id' => $warehouseA->id, 'quantity' => 30]);
        Stock::create(['product_id' => $product->id, 'warehouse_id' => $warehouseB->id, 'quantity' => 30]);

        ReorderRule::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseA->id,
            'min_level' => 10,
            'max_level' => 200,
            'reorder_quantity' => 50,
            'lead_time_days' => 7,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/low-stock');

        $response->assertStatus(200);
        $items = collect($response->json('low_stock_items'));

        $warehouseAItem = $items->firstWhere('warehouse_id', $warehouseA->id);
        $warehouseBItem = $items->firstWhere('warehouse_id', $warehouseB->id);

        // Warehouse A: 30 >= its own rule's min_level (10) — not low stock.
        expect($warehouseAItem)->toBeNull();

        // Warehouse B: no rule, falls back to the product's global
        // reorder_level (100) — 30 < 100, genuinely low stock.
        expect($warehouseBItem)->not->toBeNull();
        expect($warehouseBItem['reorder_level'])->toBe(100);
        expect($warehouseBItem['from_warehouse_rule'])->toBeFalse();
    });

    test('an inactive ReorderRule is ignored, falling back to the product default', function () {
        $product = Product::factory()->create(['reorder_level' => 100]);
        $warehouse = Warehouse::factory()->create();
        Stock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 30]);

        ReorderRule::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'min_level' => 10,
            'max_level' => 200,
            'reorder_quantity' => 50,
            'lead_time_days' => 7,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/low-stock');
        $item = collect($response->json('low_stock_items'))->firstWhere('warehouse_id', $warehouse->id);

        expect($item)->not->toBeNull();
        expect($item['reorder_level'])->toBe(100);
        expect($item['from_warehouse_rule'])->toBeFalse();
    });
});

describe('ReorderRule::scopeNeedsReorder() (was a guaranteed SQL error, never called until now)', function () {
    test('joins the real per-warehouse stock row instead of a nonexistent Product column', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        Stock::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);

        ReorderRule::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'min_level' => 10,
            'max_level' => 200,
            'reorder_quantity' => 50,
            'lead_time_days' => 7,
            'status' => 'active',
        ]);

        $results = ReorderRule::needsReorder()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->product_id)->toBe($product->id);
    });
});
