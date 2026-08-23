<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Inventory\Models\MarketplaceChannel;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;

/**
 * Chantier 8.3: ChannelController (marketplace channel connections) and
 * UnitController (units of measure CRUD) were real, fully-written controllers
 * with zero routes anywhere. BarcodeController's lookupProduct/lookupLocation/
 * stockMovement methods were real too, but only a dead lookup/stockMovement
 * combo (`barcodes/lookup`, deleted in 8.3il-6) existed — these are the real,
 * differently-named routes that were missing entirely.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function inventoryOrphanedApiTestUser(): User
{
    if (\Spatie\Permission\Models\Permission::count() === 0) {
        test()->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    // Chantier 19 Lot 4: ChannelController now scopes by the real
    // users.company_id column (was the phantom, always-null users.tenant_id
    // before this — see ChannelController's own docblock) — give this test
    // user a real company so the tenant-isolation fix can be exercised
    // meaningfully rather than everyone defaulting to the same 0 bucket.
    $company = \App\Models\Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('employee');

    return $user;
}

test('channels index lists channels for the current tenant', function () {
    $user = inventoryOrphanedApiTestUser();
    MarketplaceChannel::create([
        'tenant_id' => $user->company_id,
        'type' => 'amazon',
        'name' => 'My Amazon Store',
        'config' => ['api_key' => 'x'],
        'status' => 'inactive',
        'company_id' => $user->company_id,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/channels');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('channels connect creates a new marketplace channel', function () {
    $user = inventoryOrphanedApiTestUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/channels/amazon/connect', [
        'name' => 'My Amazon Store',
        'config' => ['api_key' => 'abc', 'seller_id' => '123'],
    ]);

    $response->assertCreated();
    expect($response->json('data.type'))->toBe('amazon');
    expect($response->json('data.company_id'))->toBe($user->company_id);
    $this->assertDatabaseHas('marketplace_channels', ['name' => 'My Amazon Store', 'type' => 'amazon']);
});

test('channels connect rejects an unknown channel type', function () {
    $user = inventoryOrphanedApiTestUser();

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/channels/shopify/connect', [
        'name' => 'X',
        'config' => [],
    ]);

    $response->assertStatus(422);
});

test('channels sync dispatches a sync job', function () {
    $user = inventoryOrphanedApiTestUser();
    $channel = MarketplaceChannel::create([
        'tenant_id' => $user->company_id,
        'type' => 'ebay',
        'name' => 'My eBay Store',
        'config' => [],
        'status' => 'active',
        'company_id' => $user->company_id,
    ]);

    \Illuminate\Support\Facades\Queue::fake();

    $response = test()->actingAs($user, 'sanctum')->postJson("/api/v1/inventory/channels/{$channel->id}/sync");

    $response->assertOk();
    \Illuminate\Support\Facades\Queue::assertPushed(\Modules\Inventory\Jobs\SyncChannelJob::class);
});

test('channels status returns connector status', function () {
    $user = inventoryOrphanedApiTestUser();
    $channel = MarketplaceChannel::create([
        'tenant_id' => $user->company_id,
        'type' => 'amazon',
        'name' => 'My Amazon Store',
        'config' => ['marketplace_id' => 'ATVPDKIKX0DER'],
        'status' => 'active',
        'company_id' => $user->company_id,
    ]);

    $response = test()->actingAs($user, 'sanctum')->getJson("/api/v1/inventory/channels/{$channel->id}/status");

    $response->assertOk();
    expect($response->json('data.type'))->toBe('amazon');
    expect($response->json('data.channel_id'))->toBe($channel->id);
});

test('units crud round-trip', function () {
    $user = inventoryOrphanedApiTestUser();

    $create = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/units', [
        'name' => 'Dozen',
        'symbol' => 'dz',
        'type' => 'unit',
    ]);
    $create->assertCreated();
    $unitId = $create->json('data.id') ?? $create->json('id');

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/units')->assertOk();

    $update = test()->actingAs($user, 'sanctum')->putJson("/api/v1/inventory/units/{$unitId}", ['name' => 'Dozen (updated)']);
    $update->assertOk();

    $this->assertDatabaseHas('inventory_units', ['id' => $unitId, 'name' => 'Dozen (updated)']);

    $delete = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/inventory/units/{$unitId}");
    $delete->assertNoContent();
});

test('units destroy is blocked when the unit is in use', function () {
    $user = inventoryOrphanedApiTestUser();
    // Chantier 32: Unit now scopes by company_id — must match the acting
    // user's own company for assertSameCompany() to pass and actually reach
    // the in-use check this test exercises.
    $unit = Unit::factory()->create(['company_id' => $user->company_id]);
    Product::factory()->create(['unit_id' => $unit->id, 'company_id' => $user->company_id]);

    $response = test()->actingAs($user, 'sanctum')->deleteJson("/api/v1/inventory/units/{$unit->id}");

    $response->assertStatus(422);
    $this->assertDatabaseHas('inventory_units', ['id' => $unit->id]);
});

test('barcode product lookup returns 404 for an unknown barcode', function () {
    $user = inventoryOrphanedApiTestUser();

    test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/barcode/product/UNKNOWN-999')->assertNotFound();
});

test('barcode product lookup finds a product by its barcode', function () {
    $user = inventoryOrphanedApiTestUser();
    // Chantier 32: Product now scopes by company_id.
    $product = Product::factory()->create(['barcode' => '1234567890123', 'company_id' => $user->company_id]);

    $response = test()->actingAs($user, 'sanctum')->getJson('/api/v1/inventory/barcode/product/1234567890123');

    $response->assertOk();
    expect($response->json('product.id'))->toBe($product->id);
});

test('barcode stock-movement records a movement for a scanned product', function () {
    $user = inventoryOrphanedApiTestUser();
    // Chantier 32: Product/Warehouse now scope by company_id.
    $product = Product::factory()->create(['barcode' => '1234567890123', 'company_id' => $user->company_id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $user->company_id]);

    $response = test()->actingAs($user, 'sanctum')->postJson('/api/v1/inventory/barcode/stock-movement', [
        'product_barcode' => '1234567890123',
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'type' => 'in',
    ]);

    $response->assertCreated();
    expect($response->json('product.id'))->toBe($product->id);
});
