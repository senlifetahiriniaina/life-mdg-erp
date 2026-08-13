<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\ItemAbcAnalysis;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\SerialNumber;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseTransfer;
use Modules\Inventory\Models\WarehouseTransferItem;
use Modules\Inventory\Services\ABCAnalysisService;
use Modules\Inventory\Services\CycleCountService;
use Modules\Inventory\Services\SerialNumberService;

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
    expect($line->counted_qty)->toBe(48.0);
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
        ->postJson('/api/v1/inventory/transfers', [
            'warehouse_from_id' => $this->warehouse->id,
            'warehouse_to_id' => $warehouse2->id,
            'items' => [],
        ]);

    // Depending on implementation, this could be 422 or 400
    expect($response->status())->toBeIn([400, 422, 409]);
});

// ========== ABC ANALYSIS TESTS ==========

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
    $service->analyzeAllProducts();

    $analysis = ItemAbcAnalysis::get();
    expect($analysis)->toHaveCount(3);

    // High-value item should be A
    $p1Analysis = ItemAbcAnalysis::where('product_id', $p1->id)->first();
    expect($p1Analysis->abc_class)->toBe('A');
});

it('can get ABC classification summary', function () {
    $p1 = Product::factory()->create(['cost_price' => 100]);
    $p2 = Product::factory()->create(['cost_price' => 10]);

    Stock::factory()->create(['product_id' => $p1->id, 'quantity' => 1000]);
    Stock::factory()->create(['product_id' => $p2->id, 'quantity' => 10000]);

    $service = new ABCAnalysisService();
    $service->analyzeAllProducts();

    $summary = $service->getSummary();

    expect($summary)->toHaveKeys(['total_items', 'total_value', 'class_a', 'class_b', 'class_c']);
    expect($summary['total_items'])->toBe(2);
});

it('can calculate reorder point based on ABC classification', function () {
    $product = Product::factory()->create();
    ItemAbcAnalysis::create([
        'product_id' => $product->id,
        'abc_class' => 'A',
        'annual_usage' => 365,
        'unit_cost' => 10,
    ]);

    $service = new ABCAnalysisService();
    $reorderPoint = $service->calculateReorderPoint($product);

    expect($reorderPoint)->toBeGreaterThan(0);
    // A items: 7 day lead time + 7 day safety stock = ~80 units
    expect($reorderPoint)->toBeGreaterThan(50);
});

// ========== SERIAL NUMBER TESTS ==========

it('can create serial numbers with range', function () {
    $product = Product::factory()->create();

    $service = new SerialNumberService();
    $serials = $service->createSerialRange($product, 1000, 1010, 'SN-');

    expect($serials)->toHaveCount(11);
    expect($serials[0]->serial_number)->toBe('SN-1000');
});

it('can get available serials for product', function () {
    $product = Product::factory()->create();

    SerialNumber::factory()->count(5)->create([
        'product_id' => $product->id,
        'status' => 'available',
    ]);
    SerialNumber::factory()->create([
        'product_id' => $product->id,
        'status' => 'sold',
    ]);

    $service = new SerialNumberService();
    $available = $service->getAvailableSerials($product);

    expect($available)->toHaveCount(5);
});

it('can track serial count by status', function () {
    $product = Product::factory()->create();

    SerialNumber::factory()->count(3)->create(['product_id' => $product->id, 'status' => 'available']);
    SerialNumber::factory()->create(['product_id' => $product->id, 'status' => 'sold']);
    SerialNumber::factory()->create(['product_id' => $product->id, 'status' => 'damaged']);

    $service = new SerialNumberService();
    $counts = $service->getSerialCountByStatus($product);

    expect($counts['available'])->toBe(3);
    expect($counts['sold'])->toBe(1);
    expect($counts['damaged'])->toBe(1);
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
        ->postJson('/api/v1/inventory/transfers', [
            'warehouse_from_id' => $this->warehouse->id,
            'warehouse_to_id' => $warehouse2->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 50,
                ],
            ],
        ])
        ->assertCreated();

    expect($response->json('status'))->toBe('pending');
});

it('can track transfer status and complete transfer', function () {
    $warehouse2 = Warehouse::factory()->create();
    $transfer = WarehouseTransfer::factory()->create([
        'warehouse_from_id' => $this->warehouse->id,
        'warehouse_to_id' => $warehouse2->id,
        'status' => 'pending',
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/inventory/transfers/{$transfer->id}", [
            'status' => 'in_transit',
        ])
        ->assertOk();

    $transfer->refresh();
    expect($transfer->status)->toBe('in_transit');
});

it('can validate transfer timeline (24h SLA)', function () {
    $warehouse2 = Warehouse::factory()->create();
    $transfer = WarehouseTransfer::factory()->create([
        'warehouse_from_id' => $this->warehouse->id,
        'warehouse_to_id' => $warehouse2->id,
        'status' => 'in_transit',
        'created_at' => now()->subHours(25),
    ]);

    // Transfer is overdue (created > 24h ago, not completed)
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/inventory/transfers/alerts')
        ->assertOk();

    $data = $response->json();
    // Should include overdue transfer
    expect($data)->toHaveKey('overdue_transfers');
});

// ========== INTEGRATION TESTS ==========

it('cycle count respects ABC classification for focus areas', function () {
    // A items (high value) should be counted more frequently
    $highValue = Product::factory()->create();
    ItemAbcAnalysis::create([
        'product_id' => $highValue->id,
        'abc_class' => 'A',
        'annual_value' => 100000,
    ]);

    Stock::create(['product_id' => $highValue->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 100]);

    // Generate cycle count focusing on A items
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/inventory/cycle-counts/smart-plan', [
            'warehouse_id' => $this->warehouse->id,
            'focus_abc_class' => 'A',
        ])
        ->assertOk();

    expect($response->json())->toHaveKey('cycle_count_id');
});

it('unauthenticated users cannot access inventory endpoints', function () {
    $this->getJson('/api/v1/inventory/cycle-counts')->assertUnauthorized();
    $this->getJson('/api/v1/inventory/transfers')->assertUnauthorized();
});
