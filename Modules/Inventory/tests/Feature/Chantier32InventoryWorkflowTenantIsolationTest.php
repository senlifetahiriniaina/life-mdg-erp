<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Carrier;
use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\EdiTransaction;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Rma;
use Modules\Inventory\Models\SeasonalFactor;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\ValuationRun;
use Modules\Inventory\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Chantier 32 (Inventory "workflow/fulfillment" half) — the fourth
 * application this session of the CRM (Chantier 10/19)/Achats (Chantier 19
 * Lot 3)/Projects (Chantier 19 Lot 2) tenant-isolation retrofit pattern.
 *
 * Mirrors Modules\CRM\tests\Feature\Chantier19CrmReauditTest.php's exact
 * structure — 2 real companies, real HTTP calls — adapted to this app's
 * Achats-style cross-company denial (404, not CRM's Policy-driven 403).
 */
function chantier32InventoryUser(string $companySuffix, string $role = 'admin'): User
{
    if (Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $company = Company::create([
        'name' => "Chantier32 Co {$companySuffix}",
        'currency' => 'MGA',
        'timezone' => 'Indian/Antananarivo',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->forceFill([
        'two_factor_enabled' => true,
        'google2fa_secret' => 'JBSWY3DPEHPK3PXP',
        'two_factor_confirmed_at' => now(),
    ])->save();
    $user->assignRole($role);

    // module:Inventory middleware gates on tenant_modules keyed by the
    // user's own id — a separate, per-user module-toggle concept, not the
    // company_id tenant-data boundary this test is about — matching
    // Chantier19CrmReauditTest.php's identical helper.
    DB::table('tenant_modules')->updateOrInsert(
        ['tenant_id' => (string) $user->id, 'module' => 'Inventory', 'department' => null],
        ['enabled' => true, 'settings' => '{}', 'updated_at' => now(), 'created_at' => now()]
    );

    return $user;
}

// ─── PickingOrder ───────────────────────────────────────────────────────────

test('picking order index only returns the caller company own orders', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    PickingOrder::factory()->create(['company_id' => $userA->company_id, 'reference' => 'PICK-A-1']);
    PickingOrder::factory()->create(['company_id' => $userB->company_id, 'reference' => 'PICK-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/picking-orders')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('PICK-A-1')->not->toContain('PICK-B-1');
});

test('company B cannot view, update, or delete company A picking order', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $po = PickingOrder::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/picking-orders/{$po->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/picking-orders/{$po->id}", ['priority' => 5])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/picking-orders/{$po->id}")->assertNotFound();
});

test('company A can still view, update, and delete its own picking order', function () {
    $userA = chantier32InventoryUser('A');
    $po = PickingOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'pending']);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/picking-orders/{$po->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/picking-orders/{$po->id}", ['priority' => 5])
        ->assertOk()->assertJsonPath('priority', 5);
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/picking-orders/{$po->id}")->assertNoContent();
});

test('picking order store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $location = Location::factory()->create(['warehouse_id' => $warehouse->id]);

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/picking-orders', [
        'warehouse_id' => $warehouse->id,
        'type' => 'pick',
        'company_id' => $userB->company_id, // must be ignored
        'lines' => [
            ['product_id' => $product->id, 'location_id' => $location->id, 'quantity_requested' => 2],
        ],
    ])->assertCreated();

    $po = PickingOrder::find($response->json('id'));
    expect((int) $po->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

test('company B cannot complete company A picking order (custom {picking} route)', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $po = PickingOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'in_progress']);

    // Route::post('picking-orders/{picking}/complete', ...) resolves against
    // a wildcard name that differs from the controller's $pickingOrder
    // param — confirmed empirically (unlike crossdock's {crossdock} vs
    // $crossdockOperation) that Laravel still binds the real record here,
    // but locking it in with a real request rather than trusting that once.
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/inventory/picking-orders/{$po->id}/complete")->assertNotFound();
    expect($po->fresh()->status)->toBe('in_progress');
});

test('company A can complete its own picking order (custom {picking} route)', function () {
    $userA = chantier32InventoryUser('A');
    $po = PickingOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'in_progress']);

    test()->actingAs($userA, 'sanctum')->postJson("/api/v1/inventory/picking-orders/{$po->id}/complete")->assertOk();
    expect($po->fresh()->status)->toBe('completed');
});

// ─── TransferOrder (no real destroy route — apiResource's destroy() is a
// pre-existing dead-route registration pointing at a method
// TransferOrderController never defines, unrelated to this chantier) ──────

test('transfer order index only returns the caller company own orders', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    TransferOrder::factory()->create(['company_id' => $userA->company_id, 'reference' => 'TXFR-A-1']);
    TransferOrder::factory()->create(['company_id' => $userB->company_id, 'reference' => 'TXFR-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/transfer-orders')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('TXFR-A-1')->not->toContain('TXFR-B-1');
});

test('company B cannot view or update company A transfer order', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $order = TransferOrder::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/transfer-orders/{$order->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/transfer-orders/{$order->id}", ['notes' => 'hacked'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->postJson("/api/v1/inventory/transfer-orders/{$order->id}/cancel")->assertNotFound();

    expect($order->fresh()->notes)->not->toBe('hacked');
});

test('company A can still view and update its own transfer order', function () {
    $userA = chantier32InventoryUser('A');
    $order = TransferOrder::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/transfer-orders/{$order->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/transfer-orders/{$order->id}", ['notes' => 'ok'])
        ->assertOk()->assertJsonPath('notes', 'ok');
});

test('transfer order store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $fromWh = Warehouse::factory()->create();
    $toWh = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/transfer-orders', [
        'from_warehouse_id' => $fromWh->id,
        'to_warehouse_id' => $toWh->id,
        'company_id' => $userB->company_id, // must be ignored
        'lines' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ])->assertCreated();

    $order = TransferOrder::find($response->json('id'));
    expect((int) $order->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── Shipment ───────────────────────────────────────────────────────────────

test('shipment index only returns the caller company own shipments', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    Shipment::factory()->create(['company_id' => $userA->company_id, 'reference' => 'SHP-A-1']);
    Shipment::factory()->create(['company_id' => $userB->company_id, 'reference' => 'SHP-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/shipments')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('SHP-A-1')->not->toContain('SHP-B-1');
});

test('company B cannot view, update, or delete company A shipment', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $shipment = Shipment::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/shipments/{$shipment->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->patchJson("/api/v1/inventory/shipments/{$shipment->id}", ['tracking_number' => 'X'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/shipments/{$shipment->id}")->assertNotFound();
});

test('company A can still view, update, and delete its own shipment', function () {
    $userA = chantier32InventoryUser('A');
    $shipment = Shipment::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/shipments/{$shipment->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->patchJson("/api/v1/inventory/shipments/{$shipment->id}", ['tracking_number' => 'TRK1'])
        ->assertOk()->assertJsonPath('data.tracking_number', 'TRK1');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/shipments/{$shipment->id}")->assertNoContent();
});

test('shipment store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $carrier = Carrier::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/shipments', [
        'carrier_id' => $carrier->id,
        'company_id' => $userB->company_id, // must be ignored
        'origin_address' => ['name' => 'A', 'street' => 'Rue 1', 'city' => 'Tana', 'zip' => '101', 'country' => 'MG'],
        'destination_address' => ['name' => 'B', 'street' => 'Rue 2', 'city' => 'Tana', 'zip' => '102', 'country' => 'MG'],
        'weight_kg' => 5,
    ])->assertOk();

    $shipment = Shipment::find($response->json('data.id'));
    expect((int) $shipment->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── Rma ────────────────────────────────────────────────────────────────────

test('rma index only returns the caller company own rmas', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    Rma::factory()->create(['company_id' => $userA->company_id, 'reference' => 'RMA-A-1']);
    Rma::factory()->create(['company_id' => $userB->company_id, 'reference' => 'RMA-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/rmas')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('RMA-A-1')->not->toContain('RMA-B-1');
});

test('company B cannot view, update, or delete company A rma', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $rma = Rma::factory()->requested()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/rmas/{$rma->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/rmas/{$rma->id}", ['reason' => 'hacked'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/rmas/{$rma->id}")->assertNotFound();

    expect($rma->fresh()->reason)->not->toBe('hacked');
});

test('company A can still view, update, and delete its own rma', function () {
    $userA = chantier32InventoryUser('A');
    $rma = Rma::factory()->requested()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/rmas/{$rma->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/rmas/{$rma->id}", ['reason' => 'ok'])
        ->assertOk()->assertJsonPath('reason', 'ok');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/rmas/{$rma->id}")->assertNoContent();
});

test('rma store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/rmas', [
        'customer_name' => 'Client X',
        'reason' => 'Défectueux',
        'company_id' => $userB->company_id, // must be ignored
        'items' => [['product_id' => 1, 'qty' => 1]],
    ])->assertCreated();

    $rma = Rma::find($response->json('id'));
    expect((int) $rma->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── CycleCount ─────────────────────────────────────────────────────────────

test('cycle count index only returns the caller company own counts', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    CycleCount::factory()->create(['company_id' => $userA->company_id, 'reference' => 'CC-A-1']);
    CycleCount::factory()->create(['company_id' => $userB->company_id, 'reference' => 'CC-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/cycle-counts')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('CC-A-1')->not->toContain('CC-B-1');
});

test('company B cannot view, update, or delete company A cycle count', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $cc = CycleCount::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft']);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/cycle-counts/{$cc->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/cycle-counts/{$cc->id}", ['notes' => 'hacked'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/cycle-counts/{$cc->id}")->assertNotFound();

    expect($cc->fresh()->notes)->not->toBe('hacked');
});

test('company A can still view, update, and delete its own cycle count', function () {
    $userA = chantier32InventoryUser('A');
    $cc = CycleCount::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft']);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/cycle-counts/{$cc->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/cycle-counts/{$cc->id}", ['notes' => 'ok'])
        ->assertOk()->assertJsonPath('notes', 'ok');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/cycle-counts/{$cc->id}")->assertNoContent();
});

test('cycle count store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/cycle-counts', [
        'warehouse_id' => $warehouse->id,
        'company_id' => $userB->company_id, // must be ignored
        'product_ids' => [$product->id],
    ])->assertCreated();

    $cc = CycleCount::find($response->json('id'));
    expect((int) $cc->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── DemandForecast ─────────────────────────────────────────────────────────

test('demand forecast index only returns the caller company own forecasts', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    DemandForecast::factory()->create(['company_id' => $userA->company_id, 'product_id' => $productA->id]);
    DemandForecast::factory()->create(['company_id' => $userB->company_id, 'product_id' => $productB->id]);

    $ids = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/demand-forecasts')->assertOk()->json('data')
    )->pluck('product_id');

    expect($ids)->toContain($productA->id)->not->toContain($productB->id);
});

test('company B cannot view, update, or delete company A demand forecast', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $forecast = DemandForecast::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/demand-forecasts/{$forecast->id}", ['forecasted_qty' => 999])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")->assertNotFound();

    expect((float) $forecast->fresh()->forecasted_qty)->not->toBe(999.0);
});

test('company A can still view, update, and delete its own demand forecast', function () {
    $userA = chantier32InventoryUser('A');
    $forecast = DemandForecast::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/demand-forecasts/{$forecast->id}", ['forecasted_qty' => 42])
        ->assertOk()->assertJsonPath('forecasted_qty', 42);
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/demand-forecasts/{$forecast->id}")->assertNoContent();
});

test('demand forecast store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $product = Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/demand-forecasts', [
        'product_id' => $product->id,
        'company_id' => $userB->company_id, // must be ignored
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'period_type' => 'monthly',
        'forecasted_qty' => 100,
    ])->assertCreated();

    $forecast = DemandForecast::find($response->json('id'));
    expect((int) $forecast->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── ValuationRun (only the real, reachable endpoints — valuation/runs,
// valuation/run, valuation/runs/{run}; the apiResource-registered
// index/show/store/update/destroy on 'valuations' is a pre-existing dead
// route pointing at ValuationController methods that don't exist,
// unrelated to tenant isolation) ────────────────────────────────────────

test('valuation run index only returns the caller company own runs', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    ValuationRun::factory()->create(['company_id' => $userA->company_id, 'name' => 'Run A']);
    ValuationRun::factory()->create(['company_id' => $userB->company_id, 'name' => 'Run B']);

    $names = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/valuation/runs')->assertOk()->json('data')
    )->pluck('name');

    expect($names)->toContain('Run A')->not->toContain('Run B');
});

test('company B cannot view company A valuation run', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $run = ValuationRun::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/valuation/runs/{$run->id}")->assertNotFound();
});

test('company A can still view its own valuation run', function () {
    $userA = chantier32InventoryUser('A');
    $run = ValuationRun::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/valuation/runs/{$run->id}")->assertOk();
});

test('valuation run store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/valuation/run', [
        'name' => 'New Run',
        'company_id' => $userB->company_id, // must be ignored
    ])->assertCreated();

    $run = ValuationRun::find($response->json('id'));
    expect((int) $run->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── SeasonalFactor ─────────────────────────────────────────────────────────

test('seasonal factor index only returns the caller company own factors', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    SeasonalFactor::factory()->create(['company_id' => $userA->company_id, 'product_id' => $productA->id]);
    SeasonalFactor::factory()->create(['company_id' => $userB->company_id, 'product_id' => $productB->id]);

    $ids = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/seasonal-factors')->assertOk()->json('data')
    )->pluck('product_id');

    expect($ids)->toContain($productA->id)->not->toContain($productB->id);
});

test('company B cannot view, update, or delete company A seasonal factor', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $factor = SeasonalFactor::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/seasonal-factors/{$factor->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/seasonal-factors/{$factor->id}", ['factor' => 9])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/seasonal-factors/{$factor->id}")->assertNotFound();

    expect((float) $factor->fresh()->factor)->not->toBe(9.0);
});

test('company A can still view, update, and delete its own seasonal factor', function () {
    $userA = chantier32InventoryUser('A');
    $factor = SeasonalFactor::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/seasonal-factors/{$factor->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/seasonal-factors/{$factor->id}", ['factor' => 1.5])
        ->assertOk()->assertJsonPath('factor', 1.5);
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/seasonal-factors/{$factor->id}")->assertNoContent();
});

test('seasonal factor store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $product = Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/seasonal-factors', [
        'product_id' => $product->id,
        'company_id' => $userB->company_id, // must be ignored
        'period_type' => 'monthly',
        'period_index' => 3,
        'factor' => 1.2,
    ])->assertCreated();

    $factor = SeasonalFactor::find($response->json('id'));
    expect((int) $factor->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── CrossdockOperation ─────────────────────────────────────────────────────

test('crossdock index only returns the caller company own operations', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    CrossdockOperation::factory()->create(['company_id' => $userA->company_id, 'product_id' => $productA->id]);
    CrossdockOperation::factory()->create(['company_id' => $userB->company_id, 'product_id' => $productB->id]);

    $ids = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/crossdock')->assertOk()->json('data')
    )->pluck('product_id');

    expect($ids)->toContain($productA->id)->not->toContain($productB->id);
});

test('company B cannot view, update, or delete company A crossdock operation', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $op = CrossdockOperation::factory()->planned()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/crossdock/{$op->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/crossdock/{$op->id}", ['qty' => 99])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/crossdock/{$op->id}")->assertNotFound();

    expect((float) $op->fresh()->qty)->not->toBe(99.0);
});

test('company A can still view, update, and delete its own crossdock operation', function () {
    $userA = chantier32InventoryUser('A');
    $op = CrossdockOperation::factory()->planned()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/crossdock/{$op->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/crossdock/{$op->id}", ['qty' => 7])
        ->assertOk()->assertJsonPath('qty', '7.00');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/crossdock/{$op->id}")->assertNoContent();
});

test('crossdock store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $product = Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/crossdock', [
        'product_id' => $product->id,
        'company_id' => $userB->company_id, // must be ignored
        'qty' => 4,
    ])->assertCreated();

    $op = CrossdockOperation::find($response->json('id'));
    expect((int) $op->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── PurchaseOrder (Inventory's own) ───────────────────────────────────────

test('inventory purchase order index only returns the caller company own orders', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    PurchaseOrder::factory()->create(['company_id' => $userA->company_id, 'reference' => 'IPO-A-1']);
    PurchaseOrder::factory()->create(['company_id' => $userB->company_id, 'reference' => 'IPO-B-1']);

    $refs = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/purchase-orders')->assertOk()->json('data')
    )->pluck('reference');

    expect($refs)->toContain('IPO-A-1')->not->toContain('IPO-B-1');
});

test('company B cannot view, update, or delete company A inventory purchase order', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $po = PurchaseOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft']);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/purchase-orders/{$po->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/purchase-orders/{$po->id}", ['notes' => 'hacked'])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/purchase-orders/{$po->id}")->assertNotFound();

    expect($po->fresh()->notes)->not->toBe('hacked');
});

test('company A can still view, update, and delete its own inventory purchase order', function () {
    $userA = chantier32InventoryUser('A');
    $po = PurchaseOrder::factory()->create(['company_id' => $userA->company_id, 'status' => 'draft']);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/purchase-orders/{$po->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->putJson("/api/v1/inventory/purchase-orders/{$po->id}", ['notes' => 'ok'])
        ->assertOk()->assertJsonPath('notes', 'ok');
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/purchase-orders/{$po->id}")->assertNoContent();
});

test('inventory purchase order store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    $supplier = Supplier::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/purchase-orders', [
        'supplier_id' => $supplier->id,
        'company_id' => $userB->company_id, // must be ignored
        'items' => [
            ['product_name' => 'Widget', 'quantity_ordered' => 10, 'unit_price' => 5],
        ],
    ])->assertCreated();

    $po = PurchaseOrder::find($response->json('id'));
    expect((int) $po->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── PickingWave (behind WavePickingController — one of the 4 controllers
// flagged as "not cleanly mapped to one of the 10 named models") ──────────

test('picking wave index only returns the caller company own waves', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    PickingWave::factory()->create(['company_id' => $userA->company_id, 'order_ids' => [111]]);
    PickingWave::factory()->create(['company_id' => $userB->company_id, 'order_ids' => [222]]);

    $orderIdLists = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/waves')->assertOk()->json('data')
    )->pluck('order_ids')->flatten();

    expect($orderIdLists)->toContain(111)->not->toContain(222);
});

test('company B cannot view, update, or delete company A picking wave', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $wave = PickingWave::factory()->open()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')->getJson("/api/v1/inventory/waves/{$wave->id}")->assertNotFound();
    test()->actingAs($userB, 'sanctum')->putJson("/api/v1/inventory/waves/{$wave->id}", ['picker_id' => null])->assertNotFound();
    test()->actingAs($userB, 'sanctum')->deleteJson("/api/v1/inventory/waves/{$wave->id}")->assertNotFound();
});

test('company A can still view and delete its own picking wave', function () {
    $userA = chantier32InventoryUser('A');
    $wave = PickingWave::factory()->open()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')->getJson("/api/v1/inventory/waves/{$wave->id}")->assertOk();
    test()->actingAs($userA, 'sanctum')->deleteJson("/api/v1/inventory/waves/{$wave->id}")->assertNoContent();
});

test('picking wave store ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');
    Product::factory()->create();

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/waves', [
        'order_ids' => [1, 2],
        'company_id' => $userB->company_id, // must be ignored
    ])->assertCreated();

    $wave = PickingWave::find($response->json('id'));
    expect((int) $wave->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── EdiTransaction (behind EdiController — no per-record show route,
// only index/create are reachable) ──────────────────────────────────────

test('edi transactions index only returns the caller company own transactions', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    EdiTransaction::create([
        'type' => '850', 'direction' => 'inbound', 'content_raw' => 'A', 'status' => 'processed',
        'occurred_at' => now(), 'company_id' => $userA->company_id,
    ]);
    EdiTransaction::create([
        'type' => '850', 'direction' => 'inbound', 'content_raw' => 'B', 'status' => 'processed',
        'occurred_at' => now(), 'company_id' => $userB->company_id,
    ]);

    $raws = collect(
        test()->actingAs($userA, 'sanctum')->getJson('/api/v1/inventory/edi/transactions')->assertOk()->json('data')
    )->pluck('content_raw');

    expect($raws)->toContain('A')->not->toContain('B');
});

test('edi receive ignores a client-supplied company_id', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $response = test()->actingAs($userA, 'sanctum')->postJson('/api/v1/inventory/edi/receive', [
        'content' => 'RANDOM EDI CONTENT',
        'company_id' => $userB->company_id, // must be ignored
    ])->assertCreated();

    $tx = EdiTransaction::find($response->json('transaction_id'));
    expect((int) $tx->company_id)->toBe((int) $userA->company_id)
        ->not->toBe((int) $userB->company_id);
});

// ─── EcommerceSyncController (touches the sibling-owned Product model, but
// this controller is this chantier's own) ────────────────────────────────

test('company B cannot trigger an ecommerce sync for company A product', function () {
    $userA = chantier32InventoryUser('A');
    $userB = chantier32InventoryUser('B');

    $product = Product::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userB, 'sanctum')
        ->postJson("/api/v1/inventory/sync/ecommerce/product/{$product->id}")
        ->assertNotFound();
});

test('company A can trigger an ecommerce sync for its own product', function () {
    $userA = chantier32InventoryUser('A');
    $product = Product::factory()->create(['company_id' => $userA->company_id]);

    test()->actingAs($userA, 'sanctum')
        ->postJson("/api/v1/inventory/sync/ecommerce/product/{$product->id}")
        ->assertOk();
});
