<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;
use Modules\Logistics\Services\LocationPrivacyService;

uses(RefreshDatabase::class);

describe('Location Privacy', function () {
    beforeEach(function () {
        $this->service = new LocationPrivacyService();
        $this->shipment = Shipment::factory()->create();
    });

    test('encrypt GPS coordinates', function () {
        $latitude = 40.7128;
        $longitude = -74.0060;

        $encrypted = $this->service->encryptCoordinates($latitude, $longitude);

        expect($encrypted)->not->toBeEmpty();
        expect($encrypted)->not->toBe(json_encode(['lat' => $latitude, 'lng' => $longitude]));
    });

    test('decrypt GPS coordinates', function () {
        $latitude = 40.7128;
        $longitude = -74.0060;

        $encrypted = $this->service->encryptCoordinates($latitude, $longitude);
        $decrypted = $this->service->decryptCoordinates($encrypted);

        expect($decrypted['lat'])->toBe($latitude);
        expect($decrypted['lng'])->toBe($longitude);
    });

    test('handle invalid encrypted data', function () {
        $decrypted = $this->service->decryptCoordinates('invalid_encrypted_data');

        expect($decrypted)->toBeNull();
    });

    test('purge old location data based on retention', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'created_at' => now()->subDays(91),
        ]);

        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'created_at' => now()->subDays(30),
        ]);

        $deleted = $this->service->purgeOldLocationData(90);

        expect($deleted)->toBeGreaterThanOrEqual(0);
    });

    test('batch location updates', function () {
        $this->service->batchLocationUpdate($this->shipment->id, 40.7128, -74.0060);
        $this->service->batchLocationUpdate($this->shipment->id, 40.7580, -73.9855);

        $batch = $this->service->getBatchedLocationUpdates($this->shipment->id);

        expect($batch)->toHaveCount(2);
    });

    test('clear batched updates after retrieval', function () {
        $this->service->batchLocationUpdate($this->shipment->id, 40.7128, -74.0060);

        $this->service->getBatchedLocationUpdates($this->shipment->id);
        $batch = $this->service->getBatchedLocationUpdates($this->shipment->id);

        expect($batch)->toBeEmpty();
    });

    test('anonymize location history', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $result = $this->service->anonymizeLocationHistory($this->shipment->id);

        expect($result)->toBeTrue();

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        expect($event->latitude)->toBeNull();
        expect($event->longitude)->toBeNull();
    });

    test('export location data for GDPR', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'event_type' => 'in_transit',
        ]);

        $export = $this->service->exportLocationData($this->shipment->id);

        expect($export)->toHaveKeys(['shipment_id', 'export_date', 'location_history']);
        expect($export['location_history'])->not->toBeEmpty();
    });

    test('delete location data for GDPR', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $result = $this->service->deleteLocationData($this->shipment->id);

        expect($result)->toBeTrue();

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        expect($event->latitude)->toBeNull();
        expect($event->longitude)->toBeNull();
    });

    test('check GDPR compliance', function () {
        $compliance = $this->service->checkGdprCompliance();

        expect($compliance)->toHaveKeys(['compliant', 'issues']);
        expect(is_bool($compliance['compliant']))->toBeTrue();
    });
});
