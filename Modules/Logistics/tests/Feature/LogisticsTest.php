<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;
use Modules\Logistics\Models\CustomsDeclaration;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\LogisticsRoute;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentLine;
use Modules\Logistics\Models\TrackingEvent;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Shipment model
// ─────────────────────────────────────────────────────────────────────────────

test('Shipment model uses correct table name', function () {
    expect((new Shipment())->getTable())->toBe('logistics_shipments');
});

test('Shipment model has correct fillable fields', function () {
    $model = new Shipment();

    expect($model->getFillable())->toContain('reference')
        ->toContain('type')
        ->toContain('status')
        ->toContain('carrier_id')
        ->toContain('tracking_number')
        ->toContain('incoterm')
        ->toContain('transport_mode')
        ->toContain('weight_kg');
});

test('Shipment model uses SoftDeletes', function () {
    expect(in_array(
        \Illuminate\Database\Eloquent\SoftDeletes::class,
        class_uses_recursive(Shipment::class)
    ))->toBeTrue();
});

test('Shipment model casts requires_cold_chain and has_hazmat as boolean', function () {
    $casts = (new Shipment())->getCasts();

    expect($casts['requires_cold_chain'])->toBe('boolean')
        ->and($casts['has_hazmat'])->toBe('boolean');
});

test('Shipment model casts weight_kg as decimal', function () {
    expect((new Shipment())->getCasts()['weight_kg'])->toContain('decimal');
});

test('Shipment isEditable returns true for draft status', function () {
    $shipment = new Shipment(['status' => 'draft']);

    expect($shipment->isEditable())->toBeTrue();
});

test('Shipment isEditable returns true for booked status', function () {
    $shipment = new Shipment(['status' => 'booked']);

    expect($shipment->isEditable())->toBeTrue();
});

test('Shipment isEditable returns false for delivered status', function () {
    $shipment = new Shipment(['status' => 'delivered']);

    expect($shipment->isEditable())->toBeFalse();
});

test('Shipment generateReference creates instance with SHP- prefix reference', function () {
    $shipment = Shipment::generateReference();

    expect($shipment->reference)->toStartWith('SHP-');
});

test('Shipment model has carrier belongs-to relation', function () {
    expect((new Shipment())->carrier())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('Shipment model has lines has-many relation', function () {
    expect((new Shipment())->lines())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Shipment model has trackingEvents has-many relation', function () {
    expect((new Shipment())->trackingEvents())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Carrier model
// ─────────────────────────────────────────────────────────────────────────────

test('Carrier model uses correct table name', function () {
    expect((new Carrier())->getTable())->toBe('logistics_carriers');
});

test('Carrier model has correct fillable fields', function () {
    $model = new Carrier();

    expect($model->getFillable())->toContain('name')
        ->toContain('code')
        ->toContain('type')
        ->toContain('contact_email')
        ->toContain('is_active')
        ->toContain('rating');
});

test('Carrier model casts is_active as boolean', function () {
    expect((new Carrier())->getCasts()['is_active'])->toBe('boolean');
});

test('Carrier model uses SoftDeletes', function () {
    expect(in_array(
        \Illuminate\Database\Eloquent\SoftDeletes::class,
        class_uses_recursive(Carrier::class)
    ))->toBeTrue();
});

test('Carrier model has rates has-many relation', function () {
    expect((new Carrier())->rates())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Carrier model has shipments has-many relation', function () {
    expect((new Carrier())->shipments())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Carrier model has deliveryRounds has-many relation', function () {
    expect((new Carrier())->deliveryRounds())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Carrier calculatePerformanceScore returns carrier rating when no recent shipments exist', function () {
    $carrier = new Carrier(['rating' => 3.5]);

    // No DB call needed — recent() will return empty collection on new model
    expect($carrier->calculatePerformanceScore())->toBe(3.5);
});

// ─────────────────────────────────────────────────────────────────────────────
// LogisticsRoute model
// ─────────────────────────────────────────────────────────────────────────────

test('LogisticsRoute model uses correct table name', function () {
    expect((new LogisticsRoute())->getTable())->toBe('logistics_routes');
});

test('LogisticsRoute model has correct fillable fields', function () {
    $model = new LogisticsRoute();

    expect($model->getFillable())->toContain('name')
        ->toContain('code')
        ->toContain('origin_country')
        ->toContain('destination_country')
        ->toContain('mode')
        ->toContain('is_active');
});

test('LogisticsRoute model casts is_active as boolean', function () {
    expect((new LogisticsRoute())->getCasts()['is_active'])->toBe('boolean');
});

test('LogisticsRoute model has carrier belongs-to relation', function () {
    expect((new LogisticsRoute())->carrier())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('LogisticsRoute model has shipments has-many relation', function () {
    expect((new LogisticsRoute())->shipments())
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// module.json
// ─────────────────────────────────────────────────────────────────────────────

test('Logistics module.json exists and has correct name', function () {
    $jsonPath = base_path('Modules/Logistics/module.json');

    if (!file_exists($jsonPath)) {
        $this->markTestSkipped('module.json not found for Logistics module.');
    }

    $json = json_decode(file_get_contents($jsonPath), true);

    expect($json['name'])->toBe('Logistics');
});
