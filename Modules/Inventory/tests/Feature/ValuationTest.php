<?php

declare(strict_types=1);

use Modules\Inventory\Models\CostLayer;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ValuationRun;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\ValuationService;


describe('Inventory FIFO/Weighted-Average Valuation', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(ValuationService::class);
        $this->product = Product::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
    });

    // ─── CostLayer model methods ──────────────────────────────────────────────

    test('CostLayer::isExhausted() returns false when is_exhausted = false', function () {
        $layer = CostLayer::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'is_exhausted' => false,
        ]);
        expect($layer->isExhausted())->toBeFalse();
    });

    test('CostLayer::isExhausted() returns true when is_exhausted = true', function () {
        $layer = CostLayer::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'is_exhausted' => true,
            'quantity_remaining' => 0,
        ]);
        expect($layer->isExhausted())->toBeTrue();
    });

    test('CostLayer::consume() partial reduces quantity_remaining', function () {
        $layer = CostLayer::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'unit_cost' => 10,
            'is_exhausted' => false,
        ]);

        $layer->consume(40);
        $layer->refresh();

        expect((float) $layer->quantity_remaining)->toEqual(60.0);
        expect($layer->is_exhausted)->toBeFalse();
    });

    test('CostLayer::consume() exact amount exhausts layer', function () {
        $layer = CostLayer::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'unit_cost' => 5,
            'is_exhausted' => false,
        ]);

        $layer->consume(50);
        $layer->refresh();

        expect((float) $layer->quantity_remaining)->toEqual(0.0);
        expect($layer->is_exhausted)->toBeTrue();
    });

    test('CostLayer::consume() more than available exhausts layer', function () {
        $layer = CostLayer::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_received' => 30,
            'quantity_remaining' => 30,
            'unit_cost' => 8,
            'is_exhausted' => false,
        ]);

        $layer->consume(50);
        $layer->refresh();

        expect((float) $layer->quantity_remaining)->toEqual(0.0);
        expect($layer->is_exhausted)->toBeTrue();
    });

    test('CostLayer::availableValue() = quantity_remaining * unit_cost', function () {
        $layer = CostLayer::factory()->make([
            'quantity_remaining' => 100,
            'unit_cost' => 15.5,
        ]);

        expect($layer->availableValue())->toEqual(1550.0);
    });

    // ─── ValuationRun model methods ───────────────────────────────────────────

    test('ValuationRun::isCompleted() returns true for completed status', function () {
        $run = ValuationRun::factory()->create(['status' => 'completed']);
        expect($run->isCompleted())->toBeTrue();
    });

    test('ValuationRun::isCompleted() returns false for draft status', function () {
        $run = ValuationRun::factory()->create(['status' => 'draft']);
        expect($run->isCompleted())->toBeFalse();
    });

    test('ValuationRun::getResults() returns empty array when results is null', function () {
        $run = ValuationRun::factory()->create(['results' => null]);
        expect($run->getResults())->toBe([]);
    });

    test('ValuationRun::getResults() returns results array', function () {
        $results = [['product_id' => 1, 'product_name' => 'Widget', 'qty' => 10, 'unit_cost' => 5.0, 'total_value' => 50.0]];
        $run = ValuationRun::factory()->create(['results' => $results]);
        expect($run->getResults())->toEqual($results);
    });

    test('ValuationRun::productCount() returns count of results', function () {
        $results = [
            ['product_id' => 1, 'product_name' => 'A', 'qty' => 5, 'unit_cost' => 10.0, 'total_value' => 50.0],
            ['product_id' => 2, 'product_name' => 'B', 'qty' => 3, 'unit_cost' => 20.0, 'total_value' => 60.0],
        ];
        $run = ValuationRun::factory()->create(['results' => $results]);
        expect($run->productCount())->toBe(2);
    });

    // ─── ValuationService ─────────────────────────────────────────────────────

    test('receiveCostLayer creates a cost layer record', function () {
        $layer = $this->service->receiveCostLayer(
            $this->product->id,
            $this->warehouse->id,
            100.0,
            25.0,
            'fifo',
            'PO-001'
        );

        expect($layer)->toBeInstanceOf(CostLayer::class);
        expect($layer->product_id)->toBe($this->product->id);
        expect($layer->warehouse_id)->toBe($this->warehouse->id);
        expect((float) $layer->quantity_received)->toEqual(100.0);
        expect((float) $layer->quantity_remaining)->toEqual(100.0);
        expect((float) $layer->unit_cost)->toEqual(25.0);
        expect((float) $layer->total_cost)->toEqual(2500.0);
        expect($layer->reference)->toBe('PO-001');
        expect($layer->is_exhausted)->toBeFalse();
    });

    test('consumeFifo consumes oldest layers first (FIFO order)', function () {
        // Layer 1: oldest
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0, 'fifo', 'PO-001');
        // Small sleep to ensure received_at ordering
        CostLayer::query()->where('reference', 'PO-001')
            ->update(['received_at' => now()->subMinutes(2)]);

        // Layer 2: newer
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 20.0, 'fifo', 'PO-002');

        // Consume 100 — should fully consume layer 1 at $10
        $cost = $this->service->consumeFifo($this->product->id, $this->warehouse->id, 100.0);

        expect($cost)->toEqual(1000.0); // 100 * 10

        $layer1 = CostLayer::where('reference', 'PO-001')->first();
        expect($layer1->is_exhausted)->toBeTrue();

        $layer2 = CostLayer::where('reference', 'PO-002')->first();
        expect($layer2->is_exhausted)->toBeFalse();
    });

    test('consumeFifo spans multiple layers', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 10.0, 'fifo', 'L1');
        CostLayer::where('reference', 'L1')->update(['received_at' => now()->subMinutes(5)]);

        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 20.0, 'fifo', 'L2');
        CostLayer::where('reference', 'L2')->update(['received_at' => now()->subMinutes(3)]);

        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 30.0, 'fifo', 'L3');

        // Consume 120: 50 from L1 (500) + 50 from L2 (1000) + 20 from L3 (600) = 2100
        $cost = $this->service->consumeFifo($this->product->id, $this->warehouse->id, 120.0);

        expect($cost)->toEqual(2100.0);

        expect(CostLayer::where('reference', 'L1')->first()->is_exhausted)->toBeTrue();
        expect(CostLayer::where('reference', 'L2')->first()->is_exhausted)->toBeTrue();

        $l3 = CostLayer::where('reference', 'L3')->first();
        expect($l3->is_exhausted)->toBeFalse();
        expect((float) $l3->quantity_remaining)->toEqual(30.0);
    });

    test('getWeightedAvgCost returns zero when no active layers', function () {
        $avg = $this->service->getWeightedAvgCost($this->product->id, $this->warehouse->id);
        expect($avg)->toEqual(0.0);
    });

    test('getWeightedAvgCost calculates correctly', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 20.0);

        // (100 * 10 + 100 * 20) / 200 = 15
        $avg = $this->service->getWeightedAvgCost($this->product->id, $this->warehouse->id);
        expect($avg)->toEqual(15.0);
    });

    test('getActiveLayers returns only non-exhausted layers in received_at ASC order', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 10.0, 'fifo', 'OLD');
        CostLayer::where('reference', 'OLD')->update(['received_at' => now()->subMinutes(10)]);

        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 20.0, 'fifo', 'NEW');

        // Exhaust OLD layer
        CostLayer::where('reference', 'OLD')->update(['is_exhausted' => true]);

        $layers = $this->service->getActiveLayers($this->product->id, $this->warehouse->id);

        expect($layers)->toHaveCount(1);
        expect($layers->first()->reference)->toBe('NEW');
    });

    test('getTotalInventoryValue sums all active layers', function () {
        $p2 = Product::factory()->create();
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);
        $this->service->receiveCostLayer($p2->id, $this->warehouse->id, 50.0, 20.0);

        $total = $this->service->getTotalInventoryValue();

        // 100 * 10 + 50 * 20 = 2000
        expect($total)->toEqual(2000.0);
    });

    test('getValuationSummary returns correct structure', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $summary = $this->service->getValuationSummary();

        expect($summary)->toHaveKeys(['total_value', 'product_count', 'warehouse_count', 'last_valuation_date']);
        expect($summary['total_value'])->toEqual(1000.0);
        expect($summary['product_count'])->toBe(1);
        expect($summary['warehouse_count'])->toBe(1);
        expect($summary['last_valuation_date'])->toBeNull();
    });

    test('getValuationSummary includes last_valuation_date after a run', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);
        $this->service->runValuation('Test Run');

        $summary = $this->service->getValuationSummary();

        expect($summary['last_valuation_date'])->toBe(now()->toDateString());
    });

    test('runValuation creates a completed ValuationRun with results', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 25.0);

        $run = $this->service->runValuation('Q1 Valuation', 'fifo');

        expect($run)->toBeInstanceOf(ValuationRun::class);
        expect($run->status)->toBe('completed');
        expect($run->isCompleted())->toBeTrue();
        expect($run->name)->toBe('Q1 Valuation');
        expect($run->method)->toBe('fifo');
        expect((float) $run->total_value)->toEqual(2500.0);
        expect($run->product_count)->toBeGreaterThan(0);
        expect($run->getResults())->not->toBeEmpty();

        $result = $run->getResults()[0];
        expect($result)->toHaveKeys(['product_id', 'product_name', 'qty', 'unit_cost', 'total_value']);
    });

    test('runValuation excludes exhausted layers', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 50.0, 10.0);
        CostLayer::query()->update(['is_exhausted' => true, 'quantity_remaining' => 0]);

        $run = $this->service->runValuation('Empty Run');

        expect((float) $run->total_value)->toEqual(0.0);
        expect($run->product_count)->toBe(0);
    });

    test('getProductValue returns correct structure', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $value = $this->service->getProductValue($this->product->id, $this->warehouse->id);

        expect($value)->toHaveKeys(['product_id', 'warehouse_id', 'quantity_on_hand', 'avg_unit_cost', 'total_value']);
        expect($value['product_id'])->toBe($this->product->id);
        expect($value['warehouse_id'])->toBe($this->warehouse->id);
    });

    test('getValueByWarehouse groups by warehouse', function () {
        $w2 = Warehouse::factory()->create();
        $p2 = Product::factory()->create();

        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);
        $this->service->receiveCostLayer($p2->id, $w2->id, 50.0, 20.0);

        $byWarehouse = $this->service->getValueByWarehouse();

        expect($byWarehouse)->toHaveCount(2);

        $warehouseIds = array_column($byWarehouse, 'warehouse_id');
        expect($warehouseIds)->toContain($this->warehouse->id);
        expect($warehouseIds)->toContain($w2->id);

        foreach ($byWarehouse as $entry) {
            expect($entry)->toHaveKeys(['warehouse_id', 'warehouse_name', 'total_value', 'product_count']);
        }
    });

    // ─── API endpoints ────────────────────────────────────────────────────────

    test('POST /api/v1/inventory/valuation/receive creates a cost layer', function () {
        $response = $this->postJson('/api/v1/inventory/valuation/receive', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'unit_cost' => 15.0,
            'method' => 'fifo',
            'reference' => 'API-PO-001',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['product_id' => $this->product->id])
            ->assertJsonFragment(['reference' => 'API-PO-001']);

        expect(CostLayer::count())->toBe(1);
    });

    test('POST /api/v1/inventory/valuation/receive validates required fields', function () {
        $response = $this->postJson('/api/v1/inventory/valuation/receive', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'warehouse_id', 'quantity', 'unit_cost']);
    });

    test('GET /api/v1/inventory/valuation/summary returns summary', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $response = $this->getJson('/api/v1/inventory/valuation/summary');

        $response->assertOk()
            ->assertJsonStructure(['total_value', 'product_count', 'warehouse_count', 'last_valuation_date']);
    });

    test('GET /api/v1/inventory/valuation/total-value returns total', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $response = $this->getJson('/api/v1/inventory/valuation/total-value');

        $response->assertOk()->assertJsonFragment(['total_value' => 1000.0]);
    });

    test('GET /api/v1/inventory/valuation/by-warehouse returns grouped data', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $response = $this->getJson('/api/v1/inventory/valuation/by-warehouse');

        $response->assertOk()->assertJsonStructure([['warehouse_id', 'warehouse_name', 'total_value', 'product_count']]);
    });

    test('GET /api/v1/inventory/valuation/product-value returns product value', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $response = $this->getJson('/api/v1/inventory/valuation/product-value?'.http_build_query([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk()
            ->assertJsonFragment(['product_id' => $this->product->id]);
    });

    test('GET /api/v1/inventory/valuation/layers returns active layers', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 10.0);

        $response = $this->getJson('/api/v1/inventory/valuation/layers?'.http_build_query([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $data = $response->json();
        expect($data)->toHaveCount(1);
    });

    test('GET /api/v1/inventory/valuation/runs returns paginated list', function () {
        ValuationRun::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/inventory/valuation/runs');

        $response->assertOk()->assertJsonStructure(['data', 'total']);
    });

    test('POST /api/v1/inventory/valuation/run creates valuation run', function () {
        $this->service->receiveCostLayer($this->product->id, $this->warehouse->id, 100.0, 25.0);

        $response = $this->postJson('/api/v1/inventory/valuation/run', [
            'name' => 'API Run Test',
            'method' => 'fifo',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'API Run Test'])
            ->assertJsonFragment(['status' => 'completed']);
    });

    test('POST /api/v1/inventory/valuation/run validates required name', function () {
        $response = $this->postJson('/api/v1/inventory/valuation/run', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name']);
    });

    test('GET /api/v1/inventory/valuation/runs/{run} returns specific run', function () {
        $run = ValuationRun::factory()->create(['name' => 'Specific Run']);

        $response = $this->getJson("/api/v1/inventory/valuation/runs/{$run->id}");

        $response->assertOk()->assertJsonFragment(['name' => 'Specific Run']);
    });

    test('GET /api/v1/inventory/valuation/runs/{run} returns 404 for missing run', function () {
        $response = $this->getJson('/api/v1/inventory/valuation/runs/99999');

        $response->assertStatus(404);
    });
});

test('unauthenticated requests are rejected', function () {
    $response = $this->getJson('/api/v1/inventory/valuation/summary');

    $response->assertStatus(401);
});
