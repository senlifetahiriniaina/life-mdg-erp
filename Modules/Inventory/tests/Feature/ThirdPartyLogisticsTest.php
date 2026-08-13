<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Services\ThirdPartyLogistics\FulfillmentByAmazonConnector;
use Modules\Inventory\Services\ThirdPartyLogistics\FulfillmentService;
use Modules\Inventory\Services\ThirdPartyLogistics\ShipBobConnector;
use Modules\Inventory\Services\ThirdPartyLogistics\ShipMonkConnector;

uses(RefreshDatabase::class);

// ─── Connector registration ───────────────────────────────────────────────────

test('can register a custom connector', function () {
    $svc       = new FulfillmentService();
    $connector = new ShipBobConnector([]);
    $svc->registerConnector('custom_shipbob', $connector);

    expect($svc->getConnector('custom_shipbob'))->toBe($connector);
});

test('available connectors returns all registered names', function () {
    $svc = new FulfillmentService();

    expect($svc->availableConnectors())->toContain('shipbob')
        ->toContain('shipmonk')
        ->toContain('fba');
});

// ─── ShipBob sandbox ──────────────────────────────────────────────────────────

test('shipbob connector is sandbox when no api_token', function () {
    $connector = new ShipBobConnector([]);
    expect($connector->isSandbox())->toBeTrue();
});

test('shipbob sandbox create order returns reference id', function () {
    $connector = new ShipBobConnector([]);
    $result    = $connector->createFulfillmentOrder([
        'order_id' => 42,
        'items'    => [['sku' => 'SKU-001', 'quantity' => 2]],
        'ship_to'  => ['name' => 'Test User', 'address' => '1 Test St', 'city' => 'NY', 'state' => 'NY', 'zip' => '10001', 'country' => 'US'],
    ]);

    expect($result['reference_id'])->toBe('SHIPBOB-42')
        ->and($result['sandbox'])->toBeTrue();
});

test('shipbob sandbox get order status returns shipped status', function () {
    $connector = new ShipBobConnector([]);
    $status    = $connector->getOrderStatus('SHIPBOB-42');

    expect($status['status'])->toBe('shipped')
        ->and($status['tracking_number'])->not->toBeNull();
});

test('shipbob sandbox inventory levels returns demo items', function () {
    $connector = new ShipBobConnector([]);
    $levels    = $connector->getInventoryLevels();

    expect($levels)->not->toBeEmpty();
    expect($levels[0])->toHaveKey('sku')
        ->toHaveKey('quantity_on_hand')
        ->toHaveKey('quantity_available');
});

// ─── ShipMonk sandbox ─────────────────────────────────────────────────────────

test('shipmonk connector is sandbox when no credentials', function () {
    $connector = new ShipMonkConnector([]);
    expect($connector->isSandbox())->toBeTrue();
});

test('shipmonk sandbox create order returns reference id', function () {
    $connector = new ShipMonkConnector([]);
    $result    = $connector->createFulfillmentOrder([
        'order_id' => 99,
        'items'    => [['sku' => 'SKU-X', 'quantity' => 1]],
        'ship_to'  => ['name' => 'Client', 'address' => '2 Test Ave', 'city' => 'LA', 'state' => 'CA', 'zip' => '90001', 'country' => 'US'],
    ]);

    expect($result['reference_id'])->toBe('SHIPMONK-99')
        ->and($result['sandbox'])->toBeTrue();
});

// ─── FBA sandbox ──────────────────────────────────────────────────────────────

test('fba connector is sandbox when no credentials', function () {
    $connector = new FulfillmentByAmazonConnector([]);
    expect($connector->isSandbox())->toBeTrue();
});

test('fba sandbox create order returns fba reference id', function () {
    $connector = new FulfillmentByAmazonConnector([]);
    $result    = $connector->createFulfillmentOrder([
        'order_id' => 'ORD-123',
        'items'    => [['sku' => 'ASIN-001', 'quantity' => 3]],
        'ship_to'  => ['name' => 'Amazon Customer', 'address' => '3 FBA Rd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'country' => 'US'],
    ]);

    expect($result['reference_id'])->toBe('FBA-ORD-123')
        ->and($result['sandbox'])->toBeTrue();
});

// ─── FulfillmentService routing ───────────────────────────────────────────────

test('route fulfillment dispatches to correct connector', function () {
    $svc    = new FulfillmentService();
    $result = $svc->routeFulfillment(55, 'shipbob', [
        'items'   => [['sku' => 'SKU-TEST', 'quantity' => 1]],
        'ship_to' => ['name' => 'Test', 'address' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => 'US'],
    ]);

    expect($result['connector'])->toBe('shipbob')
        ->and($result['order_id'])->toBe(55)
        ->and($result['reference_id'])->toBe('SHIPBOB-55');
});

test('sync inventory from fulfiller returns normalized structure', function () {
    $svc    = new FulfillmentService();
    $levels = $svc->syncInventoryFromFulfiller('shipmonk');

    expect($levels)->not->toBeEmpty();
    expect($levels[0])->toHaveKey('sku')
        ->toHaveKey('quantity_on_hand')
        ->toHaveKey('connector');
    expect($levels[0]['connector'])->toBe('shipmonk');
});

test('get connector throws when connector not registered', function () {
    $svc = new FulfillmentService();
    expect(fn () => $svc->getConnector('unknown_3pl'))
        ->toThrow(\RuntimeException::class);
});

// ─── Controller integration ───────────────────────────────────────────────────

test('GET /inventory/3pl/connectors returns connector list', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/inventory/3pl/connectors');

    $response->assertStatus(200)
        ->assertJsonStructure(['connectors' => [['name', 'sandbox']]]);
});

test('POST /inventory/3pl/fulfill routes order and returns 201', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/3pl/fulfill', [
            'order_id'  => 100,
            'connector' => 'shipbob',
            'items'     => [['sku' => 'SKU-A', 'quantity' => 2]],
            'ship_to'   => ['name' => 'Alice', 'address' => '1 St', 'city' => 'NY', 'state' => 'NY', 'zip' => '10001', 'country' => 'US'],
        ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['connector' => 'shipbob']);
});

test('POST /inventory/3pl/sync-inventory returns inventory levels', function () {
    $user = actingAsUser('admin');

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/inventory/3pl/sync-inventory', [
            'connector' => 'fba',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['inventory', 'count']);
});
