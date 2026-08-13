<?php

declare(strict_types=1);

use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotMovement;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\LotTrackingService;


// ─── Unauthenticated test (outside describe so no actingAs beforeEach) ─────────

test('LotTracking: unauthenticated requests return 401', function () {
    $response = $this->getJson('/api/v1/inventory/lots');
    $response->assertStatus(401);
});

// ─── Lot Model ────────────────────────────────────────────────────────────────

describe('Lot model', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('isActive() returns true when status is active', function () {
        $lot = Lot::factory()->create(['status' => 'active']);
        expect($lot->isActive())->toBeTrue();
    });

    test('isActive() returns false when status is not active', function () {
        $lot = Lot::factory()->create(['status' => 'expired']);
        expect($lot->isActive())->toBeFalse();
    });

    test('isExpired() returns true when status is expired', function () {
        $lot = Lot::factory()->create(['status' => 'expired']);
        expect($lot->isExpired())->toBeTrue();
    });

    test('isExpired() returns true when expiry_date is in the past', function () {
        $lot = Lot::factory()->create([
            'status' => 'active',
            'expiry_date' => now()->subDay()->format('Y-m-d'),
        ]);
        expect($lot->isExpired())->toBeTrue();
    });

    test('isExpired() returns false when expiry_date is in the future', function () {
        $lot = Lot::factory()->create([
            'status' => 'active',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
        ]);
        expect($lot->isExpired())->toBeFalse();
    });

    test('isAvailable() returns true when active and quantity > 0', function () {
        $lot = Lot::factory()->create(['status' => 'active', 'quantity' => 10]);
        expect($lot->isAvailable())->toBeTrue();
    });

    test('isAvailable() returns false when quantity is 0', function () {
        $lot = Lot::factory()->create(['status' => 'active', 'quantity' => 0]);
        expect($lot->isAvailable())->toBeFalse();
    });

    test('isAvailable() returns false when not active', function () {
        $lot = Lot::factory()->create(['status' => 'quarantine', 'quantity' => 10]);
        expect($lot->isAvailable())->toBeFalse();
    });

    test('expire() sets status to expired', function () {
        $lot = Lot::factory()->create(['status' => 'active']);
        $lot->expire();
        $lot->refresh();
        expect($lot->status)->toBe('expired');
    });

    test('quarantine() sets status to quarantine', function () {
        $lot = Lot::factory()->create(['status' => 'active']);
        $lot->quarantine();
        $lot->refresh();
        expect($lot->status)->toBe('quarantine');
    });

    test('receive() increments quantity and creates a receipt movement', function () {
        $lot = Lot::factory()->create(['quantity' => 10]);
        $lot->receive(5.0);
        $lot->refresh();

        expect((float) $lot->quantity)->toEqual(15.0);
        expect($lot->movements()->where('movement_type', 'receipt')->count())->toBe(1);
    });

    test('issue() decrements quantity and creates an issue movement', function () {
        $lot = Lot::factory()->create(['quantity' => 20]);
        $lot->issue(8.0);
        $lot->refresh();

        expect((float) $lot->quantity)->toEqual(12.0);
        expect($lot->movements()->where('movement_type', 'issue')->count())->toBe(1);
    });

    test('issue() throws InvalidArgumentException when qty exceeds available', function () {
        $lot = Lot::factory()->create(['quantity' => 5]);
        expect(fn () => $lot->issue(10.0))->toThrow(InvalidArgumentException::class);
    });
});

// ─── LotMovement Model ────────────────────────────────────────────────────────

describe('LotMovement model', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('isReceipt() returns true for receipt movement', function () {
        $movement = LotMovement::factory()->create(['movement_type' => 'receipt']);
        expect($movement->isReceipt())->toBeTrue();
    });

    test('isReceipt() returns false for non-receipt movement', function () {
        $movement = LotMovement::factory()->create(['movement_type' => 'issue']);
        expect($movement->isReceipt())->toBeFalse();
    });

    test('isIssue() returns true for issue movement', function () {
        $movement = LotMovement::factory()->create(['movement_type' => 'issue']);
        expect($movement->isIssue())->toBeTrue();
    });

    test('isIssue() returns false for non-issue movement', function () {
        $movement = LotMovement::factory()->create(['movement_type' => 'receipt']);
        expect($movement->isIssue())->toBeFalse();
    });
});

// ─── LotTrackingService ───────────────────────────────────────────────────────

describe('LotTrackingService', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(LotTrackingService::class);
        $this->product = Product::factory()->create();
    });

    test('createLot() creates and returns a Lot', function () {
        $lot = $this->service->createLot([
            'lot_number' => 'SVC-LOT-001',
            'quantity' => 100,
        ]);

        expect($lot)->toBeInstanceOf(Lot::class);
        expect($lot->lot_number)->toBe('SVC-LOT-001');
        expect((float) $lot->quantity)->toEqual(100.0);
    });

    test('receiveLot() increases quantity and returns a receipt movement', function () {
        $lot = Lot::factory()->create(['quantity' => 0]);
        $movement = $this->service->receiveLot($lot, 50.0, 'PO-001');

        $lot->refresh();
        expect((float) $lot->quantity)->toEqual(50.0);
        expect($movement->movement_type)->toBe('receipt');
        expect($movement->reference)->toBe('PO-001');
    });

    test('issueLot() decreases quantity and returns an issue movement', function () {
        $lot = Lot::factory()->create(['quantity' => 30]);
        $movement = $this->service->issueLot($lot, 10.0, 'SO-100');

        $lot->refresh();
        expect((float) $lot->quantity)->toEqual(20.0);
        expect($movement->movement_type)->toBe('issue');
        expect($movement->reference)->toBe('SO-100');
    });

    test('issueLot() throws when qty exceeds available', function () {
        $lot = Lot::factory()->create(['quantity' => 5]);
        expect(fn () => $this->service->issueLot($lot, 10.0))->toThrow(InvalidArgumentException::class);
    });

    test('getLotsByProduct() returns all lots for a product', function () {
        Lot::factory()->count(3)->create(['product_id' => $this->product->id]);
        Lot::factory()->create(); // unrelated lot

        $lots = $this->service->getLotsByProduct($this->product->id);
        expect($lots)->toHaveCount(3);
    });

    test('getExpiringLots() returns lots expiring within days ahead', function () {
        Lot::factory()->create([
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        Lot::factory()->create([
            'expiry_date' => now()->addDays(60)->format('Y-m-d'),
        ]);
        Lot::factory()->create([
            'expiry_date' => null,
        ]);

        $lots = $this->service->getExpiringLots(30);
        expect($lots)->toHaveCount(1);
    });

    test('getAvailableLots() returns only active lots with quantity > 0', function () {
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'active', 'quantity' => 10]);
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'active', 'quantity' => 0]);
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'expired', 'quantity' => 5]);

        $lots = $this->service->getAvailableLots($this->product->id);
        expect($lots)->toHaveCount(1);
    });

    test('transferLot() updates warehouse_id and returns a transfer movement', function () {
        $lot = Lot::factory()->create(['warehouse_id' => 1]);
        $movement = $this->service->transferLot($lot, 1, 2, 5.0);

        $lot->refresh();
        expect($lot->warehouse_id)->toBe(2);
        expect($movement->movement_type)->toBe('transfer');
        expect($movement->warehouse_from_id)->toBe(1);
        expect($movement->warehouse_to_id)->toBe(2);
    });

    test('quarantineLot() sets status to quarantine and creates adjustment movement', function () {
        $lot = Lot::factory()->create(['status' => 'active']);
        $this->service->quarantineLot($lot, 'Contamination suspected');

        $lot->refresh();
        expect($lot->status)->toBe('quarantine');

        $movement = $lot->movements()->where('movement_type', 'adjustment')->first();
        expect($movement)->not->toBeNull();
        expect($movement->notes)->toBe('Contamination suspected');
    });

    test('getLotStats() returns correct stats for a product', function () {
        Lot::factory()->count(2)->create(['product_id' => $this->product->id, 'status' => 'active', 'quantity' => 10]);
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'expired', 'quantity' => 5]);

        $stats = $this->service->getLotStats($this->product->id);

        expect($stats['total_lots'])->toBe(3);
        expect($stats['active_lots'])->toBe(2);
        expect($stats['expired_lots'])->toBe(1);
        expect((float) $stats['total_quantity'])->toEqual(25.0);
    });
});

// ─── API Endpoints ────────────────────────────────────────────────────────────

describe('LotTracking API', function () {

    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->product = Product::factory()->create();
    });

    test('GET /api/v1/inventory/lots returns paginated lots', function () {
        Lot::factory()->count(3)->create(['product_id' => $this->product->id]);

        $this->getJson('/api/v1/inventory/lots')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('GET /api/v1/inventory/lots filters by product_id', function () {
        Lot::factory()->count(2)->create(['product_id' => $this->product->id]);
        Lot::factory()->create(); // unrelated

        $this->getJson('/api/v1/inventory/lots?product_id='.$this->product->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('GET /api/v1/inventory/lots filters by status', function () {
        Lot::factory()->count(2)->create(['product_id' => $this->product->id, 'status' => 'active']);
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'expired']);

        $this->getJson('/api/v1/inventory/lots?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('POST /api/v1/inventory/lots creates a lot', function () {
        $this->postJson('/api/v1/inventory/lots', [
            'lot_number' => 'API-LOT-001',
            'quantity' => 50,
        ])
            ->assertStatus(201)
            ->assertJsonPath('lot_number', 'API-LOT-001');
    });

    test('POST /api/v1/inventory/lots validates required lot_number', function () {
        $this->postJson('/api/v1/inventory/lots', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lot_number']);
    });

    test('GET /api/v1/inventory/lots/expiring returns expiring lots', function () {
        Lot::factory()->create(['expiry_date' => now()->addDays(5)->format('Y-m-d')]);
        Lot::factory()->create(['expiry_date' => now()->addDays(60)->format('Y-m-d')]);

        $this->getJson('/api/v1/inventory/lots/expiring?days=30')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('GET /api/v1/inventory/lots/{lot} returns lot with movements', function () {
        $lot = Lot::factory()->create();
        LotMovement::factory()->create(['lot_id' => $lot->id]);

        $this->getJson('/api/v1/inventory/lots/'.$lot->id)
            ->assertOk()
            ->assertJsonPath('id', $lot->id)
            ->assertJsonStructure(['movements']);
    });

    test('PUT /api/v1/inventory/lots/{lot} updates a lot', function () {
        $lot = Lot::factory()->create(['notes' => null]);

        $this->putJson('/api/v1/inventory/lots/'.$lot->id, ['notes' => 'Updated note'])
            ->assertOk()
            ->assertJsonPath('notes', 'Updated note');
    });

    test('DELETE /api/v1/inventory/lots/{lot} deletes a lot', function () {
        $lot = Lot::factory()->create();

        $this->deleteJson('/api/v1/inventory/lots/'.$lot->id)
            ->assertStatus(204);

        $this->assertDatabaseMissing('inventory_lots', ['id' => $lot->id]);
    });

    test('POST /api/v1/inventory/lots/{lot}/receive increases quantity', function () {
        $lot = Lot::factory()->create(['quantity' => 0]);

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/receive', [
            'qty' => 25,
            'reference' => 'PO-999',
        ])
            ->assertStatus(201)
            ->assertJsonPath('movement_type', 'receipt');

        expect((float) $lot->fresh()->quantity)->toEqual(25.0);
    });

    test('POST /api/v1/inventory/lots/{lot}/issue decreases quantity', function () {
        $lot = Lot::factory()->create(['quantity' => 100]);

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/issue', [
            'qty' => 40,
        ])
            ->assertStatus(201)
            ->assertJsonPath('movement_type', 'issue');

        expect((float) $lot->fresh()->quantity)->toEqual(60.0);
    });

    test('POST /api/v1/inventory/lots/{lot}/issue returns 422 when qty exceeds available', function () {
        $lot = Lot::factory()->create(['quantity' => 5]);

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/issue', [
            'qty' => 100,
        ])
            ->assertStatus(422);
    });

    test('POST /api/v1/inventory/lots/{lot}/transfer updates warehouse', function () {
        $lot = Lot::factory()->create(['warehouse_id' => 1]);

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/transfer', [
            'from_warehouse_id' => 1,
            'to_warehouse_id' => 2,
            'qty' => 10,
        ])
            ->assertStatus(201)
            ->assertJsonPath('movement_type', 'transfer');

        expect($lot->fresh()->warehouse_id)->toBe(2);
    });

    test('POST /api/v1/inventory/lots/{lot}/quarantine quarantines the lot', function () {
        $lot = Lot::factory()->create(['status' => 'active']);

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/quarantine', [
            'reason' => 'Quality hold',
        ])
            ->assertOk();

        expect($lot->fresh()->status)->toBe('quarantine');
    });

    test('GET /api/v1/inventory/lots/{lot}/movements returns paginated movements', function () {
        $lot = Lot::factory()->create();
        LotMovement::factory()->count(3)->create(['lot_id' => $lot->id]);

        $this->getJson('/api/v1/inventory/lots/'.$lot->id.'/movements')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('POST /api/v1/inventory/lots/{lot}/transfer validates required fields', function () {
        $lot = Lot::factory()->create();

        $this->postJson('/api/v1/inventory/lots/'.$lot->id.'/transfer', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_warehouse_id', 'to_warehouse_id', 'qty']);
    });

    test('GET /api/v1/inventory/lots/stats/{productId} returns lot stats', function () {
        Lot::factory()->count(2)->create(['product_id' => $this->product->id, 'status' => 'active', 'quantity' => 10]);
        Lot::factory()->create(['product_id' => $this->product->id, 'status' => 'expired', 'quantity' => 0]);

        $this->getJson('/api/v1/inventory/lots/stats/'.$this->product->id)
            ->assertOk()
            ->assertJsonStructure(['total_lots', 'active_lots', 'expired_lots', 'total_quantity'])
            ->assertJsonPath('total_lots', 3)
            ->assertJsonPath('active_lots', 2)
            ->assertJsonPath('expired_lots', 1);
    });
});
