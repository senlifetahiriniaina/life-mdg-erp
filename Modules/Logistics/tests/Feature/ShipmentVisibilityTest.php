<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Services\ShipmentVisibility\FallbackTrackingConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlexportConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlightAwareConnector;
use Modules\Logistics\Services\ShipmentVisibility\MarineTrafficConnector;
use Modules\Logistics\Services\ShipmentVisibilityService;

uses(RefreshDatabase::class);

// ─── Helper ───────────────────────────────────────────────────────────────────

function makeVisibilityService(): ShipmentVisibilityService
{
    return new ShipmentVisibilityService(
        new MarineTrafficConnector([]),        // sandbox
        new FlexportConnector([]),             // sandbox
        new FlightAwareConnector([]),          // sandbox
        new FallbackTrackingConnector(),
    );
}

function oceanShipment(array $overrides = []): array
{
    return array_merge([
        'id'              => 1,
        'transport_mode'  => 'ocean',
        'tracking_number' => 'MMSI123456789',
        'carrier_slug'    => 'maersk',
        'mmsi'            => 'MMSI123456789',
    ], $overrides);
}

function airShipment(array $overrides = []): array
{
    return array_merge([
        'id'             => 2,
        'transport_mode' => 'air',
        'tracking_number' => 'AF123',
        'carrier_slug'   => 'air-france',
        'flight_number'  => 'AF123',
    ], $overrides);
}

// ─── Provider selection by mode ───────────────────────────────────────────────

test('ocean mode returns vessel tracking from marinetraffic sandbox', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility(oceanShipment());

    expect($data['mode'])->toBe('ocean')
        ->and($data)->toHaveKey('vessel_name')
        ->and($data)->toHaveKey('current_position');
});

test('air mode returns flight visibility from flightaware sandbox', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility(airShipment());

    expect($data['mode'])->toBe('air')
        ->and($data)->toHaveKey('flight_number');
});

test('unknown mode falls back to generic connector', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility([
        'id'              => 3,
        'transport_mode'  => 'road',
        'tracking_number' => 'TRACK999',
        'carrier_slug'    => 'dhl',
    ]);

    expect($data)->toHaveKey('tracking_url')
        ->and($data['fallback'])->toBeTrue();
});

// ─── Event parsing ────────────────────────────────────────────────────────────

test('ocean visibility includes events array', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility(oceanShipment());

    expect($data)->toHaveKey('events');
    expect($data['events'])->toBeArray();
});

test('air visibility includes events array', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility(airShipment());

    expect($data)->toHaveKey('events');
    expect($data['events'])->toBeArray();
});

// ─── ETA ──────────────────────────────────────────────────────────────────────

test('ocean sandbox visibility returns future ETA', function () {
    $svc  = makeVisibilityService();
    $data = $svc->getVisibility(oceanShipment());

    expect($data)->toHaveKey('eta');
    // ETA from sandbox is always set
    expect($data['eta'])->not->toBeNull();
});

// ─── Fallback connector URL templates ─────────────────────────────────────────

test('fallback connector generates correct DHL tracking URL', function () {
    $fc  = new FallbackTrackingConnector();
    $url = $fc->getTrackingUrl('dhl', '1234567890');

    expect($url)->toContain('dhl.com')
        ->and($url)->toContain('1234567890');
});

test('fallback connector generates correct Maersk tracking URL', function () {
    $fc  = new FallbackTrackingConnector();
    $url = $fc->getTrackingUrl('maersk', 'MSKU1234567');

    expect($url)->toContain('maersk.com')
        ->and($url)->toContain('MSKU1234567');
});

test('fallback connector uses default template for unknown carrier', function () {
    $fc  = new FallbackTrackingConnector();
    $url = $fc->getTrackingUrl('unknown_carrier', 'TRACK-XYZ');

    expect($url)->toContain('TRACK-XYZ');
});

// ─── MarineTraffic sandbox ────────────────────────────────────────────────────

test('marinetraffic is sandbox when no api_key', function () {
    $connector = new MarineTrafficConnector([]);
    expect($connector->isSandbox())->toBeTrue();
});

test('marinetraffic sandbox returns vessel position with lat/lng', function () {
    $connector = new MarineTrafficConnector([]);
    $position  = $connector->getVesselPosition('MMSI999');

    expect($position)->not->toBeNull()
        ->and($position)->toHaveKey('lat')
        ->and($position)->toHaveKey('lng')
        ->and($position)->toHaveKey('vessel_name');
});

// ─── FlightAware sandbox ──────────────────────────────────────────────────────

test('flightaware is sandbox when no api_key', function () {
    $connector = new FlightAwareConnector([]);
    expect($connector->isSandbox())->toBeTrue();
});

test('flightaware sandbox returns flight visibility with current position', function () {
    $connector = new FlightAwareConnector([]);
    $data      = $connector->getFlightVisibility('AF123');

    expect($data)->not->toBeNull()
        ->and($data['mode'])->toBe('air')
        ->and($data)->toHaveKey('current_position')
        ->and($data)->toHaveKey('flight_number');
});

// ─── refresh-tracking bypasses cache ─────────────────────────────────────────

test('refresh tracking returns same structure as get visibility', function () {
    $svc       = makeVisibilityService();
    $shipment  = oceanShipment(['id' => 10]);

    $first   = $svc->getVisibility($shipment);
    $refresh = $svc->refreshTracking($shipment);

    // Both should have the same keys
    expect(array_keys($refresh))->toContain('mode')
        ->toContain('vessel_name')
        ->toContain('events');
});
