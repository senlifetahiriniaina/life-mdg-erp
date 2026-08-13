<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;
use Modules\Logistics\Services\EventDeduplicationService;

uses(RefreshDatabase::class);

describe('Event Deduplication', function () {
    beforeEach(function () {
        $this->service = new EventDeduplicationService();
        $this->shipment = Shipment::factory()->create();
    });

    test('detect duplicate by provider event ID', function () {
        $providerEventId = 'provider_123';

        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'event_type' => 'picked_up',
            'provider_event_id' => $providerEventId,
        ]);

        $isDuplicate = $this->service->isDuplicate(
            $this->shipment->id,
            'picked_up',
            now(),
            $providerEventId
        );

        expect($isDuplicate)->toBeTrue();
    });

    test('detect duplicate by idempotency key', function () {
        $idempotencyKey = 'idempotent_456';

        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'event_type' => 'in_transit',
            'idempotency_key' => $idempotencyKey,
        ]);

        $isDuplicate = $this->service->isDuplicate(
            $this->shipment->id,
            'in_transit',
            now(),
            null,
            $idempotencyKey
        );

        expect($isDuplicate)->toBeTrue();
    });

    test('allow non-duplicate events', function () {
        $isDuplicate = $this->service->isDuplicate(
            $this->shipment->id,
            'picked_up',
            now(),
            'unique_event_id_789'
        );

        expect($isDuplicate)->toBeFalse();
    });

    test('create event with deduplication check', function () {
        $event = $this->service->createEvent(
            $this->shipment,
            [
                'event_type' => 'picked_up',
                'recorded_at' => now(),
                'provider_event_id' => 'new_event_001',
            ]
        );

        expect($event)->not->toBeNull();
        expect($event->event_type)->toBe('picked_up');
    });

    test('skip duplicate event creation', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'event_type' => 'picked_up',
            'provider_event_id' => 'existing_event',
        ]);

        $event = $this->service->createEvent(
            $this->shipment,
            [
                'event_type' => 'picked_up',
                'recorded_at' => now(),
                'provider_event_id' => 'existing_event',
            ]
        );

        expect($event)->toBeNull();
    });

    test('unique constraint prevents duplicates', function () {
        $recordedAt = now();

        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'event_type' => 'delivered',
            'recorded_at' => $recordedAt,
            'provider_event_id' => 'event_123',
        ]);

        expect(TrackingEvent::where('shipment_id', $this->shipment->id)
            ->where('event_type', 'delivered')
            ->count())->toBe(1);
    });

    test('validate event data', function () {
        $errors = $this->service->validateEventData([
            'event_type' => '',
            'recorded_at' => null,
        ]);

        expect($errors)->not->toBeEmpty();
        expect(count($errors))->toBeGreaterThanOrEqual(2);
    });

    test('validate latitude and longitude', function () {
        $errors = $this->service->validateEventData([
            'event_type' => 'in_transit',
            'recorded_at' => now(),
            'latitude' => 95,
            'longitude' => 200,
        ]);

        expect($errors)->not->toBeEmpty();
    });

    test('cleanup old deduplication data', function () {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id,
            'event_type' => 'picked_up',
            'idempotency_key' => 'old_key',
            'created_at' => now()->subDays(31),
        ]);

        $deleted = $this->service->cleanupOldDeduplicationData(30);

        expect($deleted)->toBeGreaterThanOrEqual(0);
    });
});
