<?php

declare(strict_types=1);

use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;


// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeMovementData(Product $product, Warehouse $warehouse, array $overrides = []): array
{
    return array_merge([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'in',
        'quantity' => 10,
        'unit_cost' => 5.0,
    ], $overrides);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

it('records a stock-in movement and increases quantity', function () {
    $service = new InventoryService;
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $movement = $service->recordMovement(makeMovementData($product, $warehouse, [
        'type' => 'in',
        'quantity' => 20,
        'unit_cost' => 3.0,
    ]));

    expect($movement)->toBeInstanceOf(StockMovement::class);
    expect($movement->exists)->toBeTrue();

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($stock)->not->toBeNull();
    expect((float) $stock->quantity)->toBe(20.0);
});

it('calculates weighted average cost on stock-in', function () {
    // Seed existing stock: qty=10, avg_cost=5.0
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'quantity' => 10,
        'reserved_quantity' => 0,
        'avg_cost' => 5.0,
    ]);

    // Add qty=5 at unit_cost=8.0 → new avg = (10*5 + 5*8) / 15 = 6.0
    $service = new InventoryService;
    $service->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'in',
        'quantity' => 5,
        'unit_cost' => 8.0,
    ]);

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect((float) $stock->quantity)->toBe(15.0);
    expect(round((float) $stock->avg_cost, 4))->toBe(6.0);
});

it('records a stock-out movement and decreases quantity', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Seed stock so there is enough to draw from
    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'quantity' => 50,
        'reserved_quantity' => 0,
        'avg_cost' => 4.0,
    ]);

    $service = new InventoryService;
    $movement = $service->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'out',
        'quantity' => 15,
    ]);

    expect($movement)->toBeInstanceOf(StockMovement::class);

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect((float) $stock->quantity)->toBe(35.0);
});

it('applies a stock adjustment (positive)', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'quantity' => 20,
        'reserved_quantity' => 0,
        'avg_cost' => 10.0,
    ]);

    $service = new InventoryService;
    $service->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'adjustment',
        'quantity' => 5,   // positive delta
    ]);

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect((float) $stock->quantity)->toBe(25.0);
});

it('applies a stock adjustment (negative)', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'quantity' => 20,
        'reserved_quantity' => 0,
        'avg_cost' => 10.0,
    ]);

    $service = new InventoryService;
    $service->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'adjustment',
        'quantity' => -8,  // negative delta
    ]);

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect((float) $stock->quantity)->toBe(12.0);
});

it('creates a new stock record when none exists for the product/warehouse', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Confirm no stock row exists yet
    expect(Stock::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->exists())->toBeFalse();

    $service = new InventoryService;
    $service->recordMovement([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'type' => 'in',
        'quantity' => 30,
        'unit_cost' => 7.5,
    ]);

    $stock = Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($stock)->not->toBeNull();
    expect((float) $stock->quantity)->toBe(30.0);
    expect(round((float) $stock->avg_cost, 4))->toBe(7.5);
});
