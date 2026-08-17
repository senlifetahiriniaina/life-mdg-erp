<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 8.3: 6 routes pointed at controller methods that didn't exist
 * (fatal "call to undefined method" on every request) — plus a 7th,
 * discovered while fixing these: Route::apiResource('barcodes', ...)
 * registered index/show/store/update/destroy against a controller with
 * none of them and no backing model. `demand-forecasts/expiring`,
 * `picking-orders/{id}/record`, `cycle-counts/{id}/record`,
 * `barcodes/lookup`, `seasonal-factors/upsert`, and the barcodes
 * apiResource were deleted (dead concepts, or 100% redundant with an
 * already-real, already-routed endpoint — pickLine/countLine/
 * lookupProduct+lookupLocation/store's own updateOrCreate()
 * respectively). `picking-orders/next` got a real implementation.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function inventoryDeadRoutesTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    $user = User::factory()->create();
    $user->assignRole('employee');

    return $user;
}

test('picking-orders/next returns the next pending unassigned order', function () {
    $user = inventoryDeadRoutesTestUser();
    $warehouse = Warehouse::factory()->create();
    $order = PickingOrder::factory()->create([
        'warehouse_id' => $warehouse->id,
        'status' => 'pending',
        'assigned_to' => null,
        'priority' => 1,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/picking-orders/next');

    $response->assertOk();
    expect($response->json('id'))->toBe($order->id);
});

test('picking-orders/next returns a friendly message when queue is empty', function () {
    $user = inventoryDeadRoutesTestUser();

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/picking-orders/next');

    $response->assertOk();
    expect($response->json('message'))->toBe('No pending picking orders.');
});

test('deleted dead routes no longer exist', function () {
    $user = inventoryDeadRoutesTestUser();

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/demand-forecasts/expiring')->assertNotFound();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/picking-orders/1/record')->assertNotFound();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/cycle-counts/1/record')->assertNotFound();
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/barcodes/lookup')->assertNotFound();
    // seasonal-factors/{seasonal_factor} PUT/DELETE still exist (apiResource), so the URI
    // matches a route pattern with a different verb - Laravel returns 405, not 404.
    test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/seasonal-factors/upsert')->assertStatus(405);
    test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/barcodes')->assertNotFound();
});

test('seasonal-factors store already upserts by product/category+period', function () {
    $user = inventoryDeadRoutesTestUser();
    $product = Product::factory()->create();

    $first = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/seasonal-factors', [
        'product_id' => $product->id,
        'period_type' => 'monthly',
        'period_index' => 12,
        'factor' => 1.5,
    ]);
    $first->assertCreated();

    $second = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/seasonal-factors', [
        'product_id' => $product->id,
        'period_type' => 'monthly',
        'period_index' => 12,
        'factor' => 2.0,
    ]);
    $second->assertOk();
    expect((float) $second->json('factor'))->toBe(2.0);

    expect(\Modules\Inventory\Models\SeasonalFactor::where('product_id', $product->id)->count())->toBe(1);
});
