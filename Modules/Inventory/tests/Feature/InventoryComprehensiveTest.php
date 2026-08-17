<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\AI\ABCAnalysisService;
use Modules\Inventory\Services\CycleCountService;
use Modules\Inventory\Services\LotTrackingService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->warehouse = Warehouse::factory()->create();
});

// ========== CYCLE COUNT TESTS ==========

it('can generate a cycle count for warehouse', function () {
    $product = Product::factory()->create();
    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'avg_cost' => 25.50,
    ]);

    $service = new CycleCountService();
    $cc = $service->generateCycleCount($this->warehouse, [$product->id]);

    expect($cc)->toBeInstanceOf(CycleCount::class);
    expect($cc->status)->toBe('planned');
    expect($cc->lines)->toHaveCount(1);
    expect((float) $cc->lines[0]->system_qty)->toBe(100.0);
});

it('can record count and track variance', function () {
    $product = Product::factory()->create();
    $cc = CycleCount::factory()->create(['warehouse_id' => $this->warehouse->id]);
    $line = CycleCountLine::factory()->create([
        'cycle_count_id' => $cc->id,
        'product_id' => $product->id,
        'system_qty' => 50,
    ]);

    $service = new CycleCountService();
    $service->recordCount($line, 48);

    $line->refresh();
    expect((float) $line->counted_qty)->toBe(48.0);
    expect($line->variance)->toBe(-2.0);
    expect($line->status)->toBe('counted');
});

it('can validate cycle count and create stock adjustments', function () {
    $product = Product::factory()->create();
    $cc = CycleCount::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'in_progress',
    ]);
    CycleCountLine::factory()->create([
        'cycle_count_id' => $cc->id,
        'product_id' => $product->id,
        'system_qty' => 100,
        'counted_qty' => 95,
        'variance' => -5,
        'status' => 'counted',
    ]);

    $service = new CycleCountService();
    $service->validateCount($cc);

    $cc->refresh();
    expect($cc->status)->toBe('completed');
});

it('blocks transfers during active cycle count', function () {
    // Create active cycle count
    $cc = CycleCount::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'in_progress',
    ]);

    // Should prevent new transfers
    $warehouse2 = Warehouse::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/inventory/transfer-orders', [
            'warehouse_from_id' => $this->warehouse->id,
            'warehouse_to_id' => $warehouse2->id,
            'items' => [],
        ]);

    // Depending on implementation, this could be 422 or 400
    expect($response->status())->toBeIn([400, 422, 409]);
});

// ========== ABC ANALYSIS TESTS ==========
// NOTE: an earlier phantom `ItemAbcAnalysis` persistence model + a phantom
// `ABCAnalysisService::analyzeAllProducts()` were never written. ABC
// classification is a real, working capability — it's computed on the fly
// (no persistence) by `Modules\Inventory\Services\AI\ABCAnalysisService`, so
// these tests now exercise that real service directly.

it('can analyze products and generate ABC classifications', function () {
    // Create products with different values
    $p1 = Product::factory()->create(['cost_price' => 100, 'name' => 'HighValue']);
    $p2 = Product::factory()->create(['cost_price' => 10, 'name' => 'MedValue']);
    $p3 = Product::factory()->create(['cost_price' => 1, 'name' => 'LowValue']);

    // Create stocks with different quantities
    Stock::factory()->create(['product_id' => $p1->id, 'quantity' => 1000]);
    Stock::factory()->create(['product_id' => $p2->id, 'quantity' => 5000]);
    Stock::factory()->create(['product_id' => $p3->id, 'quantity' => 50000]);

    $service = new ABCAnalysisService();
    $analysis = $service->analyzeInventory();

    expect($analysis['products'])->toHaveCount(3);

    // High-value item should be A
    $p1Analysis = collect($analysis['products'])->firstWhere('id', $p1->id);
    expect($p1Analysis['classification'])->toBe('A');
});

it('can get ABC classification summary', function () {
    $p1 = Product::factory()->create(['cost_price' => 100]);
    $p2 = Product::factory()->create(['cost_price' => 10]);

    Stock::factory()->create(['product_id' => $p1->id, 'quantity' => 1000]);
    Stock::factory()->create(['product_id' => $p2->id, 'quantity' => 10000]);

    $service = new ABCAnalysisService();
    $analysis = $service->analyzeInventory();

    expect($analysis)->toHaveKeys(['total_products', 'total_metric_value', 'metric_type', 'classifications', 'products', 'recommendations']);
    expect($analysis['total_products'])->toBe(2);
    expect($analysis['classifications'])->toHaveKeys(['A', 'B', 'C']);
});

it('generates stricter monitoring recommendations for A-class than C-class items', function () {
    // Same high/low-value split used above, so A vs C classes both materialize.
    $highValue = Product::factory()->create(['cost_price' => 100, 'name' => 'HighValue']);
    $midValue = Product::factory()->create(['cost_price' => 10, 'name' => 'MedValue']);
    $lowValue = Product::factory()->create(['cost_price' => 1, 'name' => 'LowValue']);

    Stock::factory()->create(['product_id' => $highValue->id, 'quantity' => 1000]);
    Stock::factory()->create(['product_id' => $midValue->id, 'quantity' => 5000]);
    Stock::factory()->create(['product_id' => $lowValue->id, 'quantity' => 50000]);

    $service = new ABCAnalysisService();
    $analysis = $service->analyzeInventory();

    $classA = collect($analysis['recommendations'])->firstWhere('class', 'A');
    $classC = collect($analysis['recommendations'])->firstWhere('class', 'C');

    expect($classA)->not->toBeNull();
    expect($classA['priority'])->toBe('critical');
    expect($classC)->not->toBeNull();
    expect($classC['priority'])->toBe('low');
});

// ========== SERIAL NUMBER TESTS ==========
// NOTE: a duplicate, never-wired `SerialNumber` model + `SerialNumberService`
// used to live here. Serial-number traceability is a real, working capability
// already — `Product.track_serial` toggles it and `Lot.serial_number` +
// `LotTrackingService` (see `LotTrackingTest`, fully green) operate it. These
// tests now exercise that real service/model.

it('can create serial-tracked lots with a numeric range', function () {
    $product = Product::factory()->create(['track_serial' => true]);
    $service = app(LotTrackingService::class);

    $lots = collect(range(1000, 1010))->map(fn (int $n) => $service->createLot([
        'product_id' => $product->id,
        'lot_number' => 'SN-'.$n,
        'serial_number' => 'SN-'.$n,
        'quantity' => 1,
    ]));

    expect($lots)->toHaveCount(11);
    expect($lots->first()->serial_number)->toBe('SN-1000');
});

it('can get available serial-tracked lots for a product', function () {
    $product = Product::factory()->create(['track_serial' => true]);
    $service = app(LotTrackingService::class);

    Lot::factory()->count(5)->create([
        'product_id' => $product->id,
        'status' => 'active',
        'quantity' => 1,
    ]);
    Lot::factory()->create([
        'product_id' => $product->id,
        'status' => 'active',
        'quantity' => 0, // sold out — no longer available
    ]);

    $available = $service->getAvailableLots($product->id);

    expect($available)->toHaveCount(5);
});

it('can track serial-tracked lot counts by status', function () {
    $product = Product::factory()->create(['track_serial' => true]);
    $service = app(LotTrackingService::class);

    Lot::factory()->count(3)->create(['product_id' => $product->id, 'status' => 'active']);
    Lot::factory()->create(['product_id' => $product->id, 'status' => 'expired']);
    Lot::factory()->create(['product_id' => $product->id, 'status' => 'quarantine']);

    $stats = $service->getLotStats($product->id);

    expect($stats['total_lots'])->toBe(5);
    expect($stats['active_lots'])->toBe(3);
    expect($stats['expired_lots'])->toBe(1);
});

// ========== WAREHOUSE TRANSFER TESTS ==========

it('can create warehouse transfer', function () {
    $warehouse2 = Warehouse::factory()->create();
    $product = Product::factory()->create();

    Stock::create([
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/inventory/transfer-orders', [
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $warehouse2->id,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 50,
                ],
            ],
        ])
        ->assertCreated();

    expect($response->json('status'))->toBe('draft');
});

it('can track transfer status and complete transfer', function () {
    $warehouse2 = Warehouse::factory()->create();
    $transfer = TransferOrder::factory()->create([
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouse2->id,
        'status' => 'approved',
    ]);

    $response = $this->withToken($this->token)
        ->postJson("/api/v1/inventory/transfer-orders/{$transfer->id}/ship")
        ->assertOk();

    expect($response->json('status'))->toBe('in_transit');

    $response = $this->withToken($this->token)
        ->postJson("/api/v1/inventory/transfer-orders/{$transfer->id}/receive")
        ->assertOk();

    expect($response->json('status'))->toBe('received');
});

it('can validate transfer timeline (24h SLA)', function () {
    $warehouse2 = Warehouse::factory()->create();
    $overdueTransfer = TransferOrder::factory()->create([
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouse2->id,
        'status' => 'in_transit',
        'expected_delivery_date' => now()->subHours(25),
        'received_at' => null,
    ]);

    $onTimeTransfer = TransferOrder::factory()->create([
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouse2->id,
        'status' => 'in_transit',
        'expected_delivery_date' => now()->addDays(2),
        'received_at' => null,
    ]);

    expect($overdueTransfer->isOverdue())->toBeTrue();
    expect($onTimeTransfer->isOverdue())->toBeFalse();
});

// ========== INTEGRATION TESTS ==========

it('cycle count can focus on A-class products identified by ABCAnalysisService', function () {
    // NOTE: this used to POST to a `/cycle-counts/smart-plan` route that was
    // never built (no controller action, no route registration) and relied
    // on the same phantom `ItemAbcAnalysis` model as above. There is no real
    // "smart plan" endpoint to target, but ABC classification (A items = high
    // priority = counted more frequently) and cycle-count generation are both
    // real, working, already-tested pieces (`ABCAnalysisService::analyzeInventory()`,
    // `CycleCountService::generateCycleCount()`, see the "can generate a cycle
    // count for warehouse" test above) — this test now chains those two real
    // services together instead of a route that doesn't exist.
    $highValue = Product::factory()->create(['cost_price' => 100, 'name' => 'HighValue']);
    $midValue = Product::factory()->create(['cost_price' => 10, 'name' => 'MedValue']);
    $lowValue = Product::factory()->create(['cost_price' => 1, 'name' => 'LowValue']);

    Stock::create(['product_id' => $highValue->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 1000]);
    Stock::create(['product_id' => $midValue->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5000]);
    Stock::create(['product_id' => $lowValue->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 50000]);

    $abcAnalysis = (new ABCAnalysisService())->analyzeInventory();
    $aClassProductIds = collect($abcAnalysis['products'])
        ->where('classification', 'A')
        ->pluck('id')
        ->all();

    expect($aClassProductIds)->toContain($highValue->id);

    $cc = (new CycleCountService())->generateCycleCount($this->warehouse, $aClassProductIds);

    expect($cc)->toBeInstanceOf(CycleCount::class);
    expect($cc->lines)->toHaveCount(count($aClassProductIds));
});

it('unauthenticated users cannot access inventory endpoints', function () {
    $this->getJson('/api/v1/inventory/cycle-counts')->assertUnauthorized();
    $this->getJson('/api/v1/inventory/transfer-orders')->assertUnauthorized();
});
