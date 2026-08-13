<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\CRM\Models\Contact;
use Modules\Core\Models\SyncQueue;
use Modules\Core\Services\SyncService;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Return a base movement array for the given product and warehouse.
 * location_id is intentionally omitted so Stock::firstOrCreate uses null.
 */
function baseMovement(Product $product, Warehouse $warehouse, string $type = 'in', float $qty = 1.0, float $unitCost = 0.0): array
{
    return [
        'product_id'   => $product->id,
        'warehouse_id' => $warehouse->id,
        'type'         => $type,
        'quantity'     => $qty,
        'unit_cost'    => $unitCost,
    ];
}

/**
 * Return the current Stock record for a product/warehouse combination,
 * freshly fetched from the database.
 */
function freshStock(Product $product, Warehouse $warehouse): Stock
{
    return Stock::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->whereNull('location_id')
        ->firstOrFail();
}

// ---------------------------------------------------------------------------
// 1. Sequential accumulation — no movement is silently lost
// ---------------------------------------------------------------------------

it('correctly accumulates stock from multiple sequential movements', function () {
    $service   = new InventoryService();
    $product   = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Simulate 10 individual "in" movements of qty=1 each.
    // In a true concurrent scenario these would be issued in parallel; here we
    // issue them sequentially to verify that every call is persisted and the
    // running total is never corrupted by a lost-update bug.
    for ($i = 0; $i < 10; $i++) {
        $service->recordMovement(baseMovement($product, $warehouse, 'in', 1.0));
    }

    $stock = freshStock($product, $warehouse);

    expect((float) $stock->quantity)->toBe(10.0);
});

// ---------------------------------------------------------------------------
// 2. Weighted-average cost calculation across sequential in-movements
// ---------------------------------------------------------------------------

it('calculates correct average cost after multiple stock-in operations', function () {
    $service   = new InventoryService();
    $product   = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Move 1: qty=10 @ 5.00  →  avg = 5.00
    $service->recordMovement(baseMovement($product, $warehouse, 'in', 10.0, 5.00));

    $stock = freshStock($product, $warehouse);
    expect((float) $stock->quantity)->toBe(10.0);
    expect((float) $stock->avg_cost)->toBe(5.0);

    // Move 2: qty=5 @ 8.00  →  avg = ((10*5) + (5*8)) / 15 = 90/15 = 6.00
    $service->recordMovement(baseMovement($product, $warehouse, 'in', 5.0, 8.00));

    $stock = freshStock($product, $warehouse);
    expect((float) $stock->quantity)->toBe(15.0);
    expect((float) $stock->avg_cost)->toBe(round(6.00, 2));

    // Move 3: qty=5 @ 11.00  →  avg = ((15*6) + (5*11)) / 20 = (90+55)/20 = 7.25
    $service->recordMovement(baseMovement($product, $warehouse, 'in', 5.0, 11.00));

    $stock = freshStock($product, $warehouse);
    expect((float) $stock->quantity)->toBe(20.0);
    expect((float) $stock->avg_cost)->toBe(round(7.25, 2));
});

// ---------------------------------------------------------------------------
// 3. Dispatch below zero — guard / known-gap documentation
// ---------------------------------------------------------------------------

it('does not allow stock to go below zero on dispatch', function () {
    $service   = new InventoryService();
    $product   = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    // Seed 5 units.
    $service->recordMovement(baseMovement($product, $warehouse, 'in', 5.0));

    // Attempt to dispatch 10 units — more than available.
    // The service uses `decrement('quantity', abs($qty))` for 'out'/'dispatch'
    // movements which does NOT guard against negative values (unlike 'adjustment'
    // which wraps with max(0, ...)). This is a KNOWN GAP: a concurrent or
    // erroneous dispatch can push quantity below zero. The assertion below
    // documents the *actual* current behaviour so that a future fix will cause
    // the test to fail and be updated deliberately.
    $service->recordMovement(baseMovement($product, $warehouse, 'dispatch', 10.0));

    $stock = freshStock($product, $warehouse);
    $qty   = (float) $stock->quantity;

    // Ideal post-condition: quantity should never be negative.
    // Current behaviour: decrement is unchecked, so quantity becomes -5.
    // If this assertion fails after a fix is applied, update the comment and
    // flip to `expect($qty)->toBeGreaterThanOrEqual(0.0)`.
    //
    // NOTE: known gap — 'out'/'dispatch' movements do not guard against negative
    // stock. To fix, replace `$stock->decrement('quantity', abs($qty))` in
    // InventoryService::updateStock() with a clamped update similar to the
    // 'adjustment' branch.
    // Fixed: dispatch is now an outflow clamped at zero, so stock never goes negative.
    expect($qty)->toBeGreaterThanOrEqual(0.0);
});

// ---------------------------------------------------------------------------
// 4. Duplicate sync mutation — idempotency / unique-constraint behaviour
// ---------------------------------------------------------------------------

it('handles a duplicate sync mutation gracefully', function () {
     $user = actingAsUser('employee');
    $service = new SyncService();

    $mutationId = (string) Str::uuid();

    $mutation = [
        'id'               => $mutationId,
        'entity_type'      => 'crm_contact',
        'entity_id'        => null,
        'operation'        => 'create',
        'client_timestamp' => now()->toISOString(),
        'payload'          => [
            'first_name' => 'Alice',
            'last_name'  => 'Duplicate',
            'email'      => 'alice.duplicate@example.com',
            'status'     => 'active',
        ],
    ];

    // First push — must always succeed.
    $first = $service->push($user->id, [$mutation]);
    expect($first['applied'])->toBe(1);
    expect($first['failed'])->toBe(0);

    // Second push with the identical mutation id.
    // The sync_queue table uses the mutation id as its primary key (string,
    // non-incrementing). A second insert with the same UUID will throw a DB
    // unique-constraint violation, which SyncService catches and records as a
    // failed mutation.
    //
    // Current behaviour: the second call fails because the SyncQueue insert
    // collides on the primary key. The contact row itself is NOT duplicated
    // (the insert into crm_contacts never reaches the DB::transaction call),
    // which is the important safety property.
    //
    // If the service is later made truly idempotent (skip already-applied ids),
    // this test should be updated to assert failed=0 and applied=1 for the
    // second call.
    $second = $service->push($user->id, [$mutation]);

    // At least one of the two calls applied the mutation successfully.
    expect($first['applied'] + $second['applied'])->toBeGreaterThanOrEqual(1);

    // Exactly one contact record should exist regardless of retry behaviour.
    expect(Contact::where('email', 'alice.duplicate@example.com')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// 5. Multiple ordered sync mutations are all applied
// ---------------------------------------------------------------------------

it('applies multiple sync mutations in order', function () {
     $user = actingAsUser('employee');
    $service = new SyncService();

    $contacts = [
        ['first_name' => 'Bob',     'last_name' => 'Alpha',   'email' => 'bob.alpha@example.com',   'status' => 'active'],
        ['first_name' => 'Carol',   'last_name' => 'Beta',    'email' => 'carol.beta@example.com',   'status' => 'active'],
        ['first_name' => 'Dave',    'last_name' => 'Gamma',   'email' => 'dave.gamma@example.com',   'status' => 'active'],
    ];

    $mutations = array_map(static function (array $payload): array {
        return [
            'id'               => (string) Str::uuid(),
            'entity_type'      => 'crm_contact',
            'entity_id'        => null,
            'operation'        => 'create',
            'client_timestamp' => now()->toISOString(),
            'payload'          => $payload,
        ];
    }, $contacts);

    $results = $service->push($user->id, $mutations);

    expect($results['applied'])->toBe(3);
    expect($results['failed'])->toBe(0);

    // All three contacts must exist in the database.
    expect(Contact::count())->toBe(3);

    foreach ($contacts as $contact) {
        expect(Contact::where('email', $contact['email'])->exists())->toBeTrue();
    }

    // The sync queue should record all three mutations as applied.
    expect(
        SyncQueue::where('entity_type', 'crm_contact')
            ->where('status', 'applied')
            ->count()
    )->toBe(3);
});
