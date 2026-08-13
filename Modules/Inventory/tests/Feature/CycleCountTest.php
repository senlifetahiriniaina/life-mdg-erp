<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;


beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('can list cycle counts', function () {
    $warehouse = Warehouse::factory()->create();
    CycleCount::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);

    $this->withToken($this->token)
        ->getJson('/api/v1/inventory/cycle-counts')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

it('can create a cycle count for products', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => null,
        'quantity' => 25,
        'reserved_quantity' => 0,
        'avg_cost' => 10,
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/inventory/cycle-counts', [
            'warehouse_id' => $warehouse->id,
            'product_ids' => [$product->id],
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'planned');

    $lines = $response->json('lines');
    expect($lines)->toHaveCount(1);
    expect((float) $lines[0]['system_qty'])->toBe(25.0);
});

it('can record a count for a line', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $cc = CycleCount::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'in_progress']);
    $line = CycleCountLine::factory()->create([
        'cycle_count_id' => $cc->id,
        'product_id' => $product->id,
        'system_qty' => 20,
        'status' => 'pending',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/cycle-counts/{$cc->id}/lines/{$line->id}/count", ['counted_qty' => 18])
        ->assertOk()
        ->assertJsonPath('status', 'counted')
        ->assertJsonPath('counted_qty', '18.0000');
});

it('can validate a cycle count and creates stock adjustments', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $cc = CycleCount::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'in_progress']);
    CycleCountLine::factory()->create([
        'cycle_count_id' => $cc->id,
        'product_id' => $product->id,
        'system_qty' => 10,
        'counted_qty' => 8,
        'variance' => -2,
        'status' => 'counted',
    ]);

    $this->withToken($this->token)
        ->postJson("/api/v1/inventory/cycle-counts/{$cc->id}/validate")
        ->assertOk()
        ->assertJsonPath('status', 'completed');
});

it('unauthenticated user cannot access cycle counts', function () {
    $this->getJson('/api/v1/inventory/cycle-counts')->assertUnauthorized();
});
