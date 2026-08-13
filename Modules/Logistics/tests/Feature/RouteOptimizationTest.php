<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\DeliveryStop;
use Modules\Logistics\Models\Location;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\RouteOptimizationService;

uses(RefreshDatabase::class);

describe('Route Optimization', function () {
    beforeEach(function () {
        $this->service = new RouteOptimizationService();
        $this->round = DeliveryRound::factory()->create();
    });

    test('optimize delivery route with multiple stops', function () {
        $locations = Location::factory()->count(3)->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        foreach ($locations as $index => $location) {
            $shipment = Shipment::factory()->create();
            DeliveryStop::factory()->create([
                'delivery_round_id' => $this->round->id,
                'shipment_id' => $shipment->id,
                'location_id' => $location->id,
                'sequence' => $index + 1,
            ]);
        }

        $result = $this->service->optimizeRoute($this->round);

        expect($result)->toHaveKeys(['total_distance_km', 'estimated_time_minutes', 'stops']);
        expect($result['stops'])->not->toBeEmpty();
    });

    test('route optimization returns from cache', function () {
        $result1 = $this->service->optimizeRoute($this->round);
        $result2 = $this->service->optimizeRoute($this->round);

        expect($result1)->toBe($result2);
    });

    test('get optimization comparison', function () {
        Location::factory()->count(3)->create();
        DeliveryStop::factory()->count(3)->create([
            'delivery_round_id' => $this->round->id,
        ]);

        $comparison = $this->service->getOptimizationComparison($this->round);

        expect($comparison)->toHaveKeys(['current', 'optimized', 'improvement']);
        expect($comparison['improvement'])->toHaveKeys(['distance_saved_km', 'time_saved_minutes', 'distance_percent']);
    });

    test('clear route optimization cache', function () {
        $this->service->optimizeRoute($this->round);
        $this->service->clearCache($this->round->id);

        // Cache should be cleared - next call would recalculate
        expect(cache("route_optimization:{$this->round->id}"))->toBeNull();
    });

    test('suggest alternative routes', function () {
        DeliveryStop::factory()->count(3)->create([
            'delivery_round_id' => $this->round->id,
        ]);

        $alternatives = $this->service->getSuggestedAlternatives($this->round, 3);

        expect($alternatives)->toHaveCount(3);
    });

    test('handle single stop route', function () {
        $location = Location::factory()->create();
        $shipment = Shipment::factory()->create();
        DeliveryStop::factory()->create([
            'delivery_round_id' => $this->round->id,
            'shipment_id' => $shipment->id,
            'location_id' => $location->id,
        ]);

        $result = $this->service->optimizeRoute($this->round);

        expect($result)->toHaveKeys(['total_distance_km', 'estimated_time_minutes', 'stops']);
    });

    test('handle no stops route', function () {
        $result = $this->service->optimizeRoute($this->round);

        expect($result['stops'])->toBeEmpty();
    });

    test('calculate route metrics correctly', function () {
        $location1 = Location::factory()->create([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $location2 = Location::factory()->create([
            'latitude' => 40.7580,
            'longitude' => -73.9855,
        ]);

        $shipment1 = Shipment::factory()->create();
        $shipment2 = Shipment::factory()->create();

        DeliveryStop::factory()->create([
            'delivery_round_id' => $this->round->id,
            'shipment_id' => $shipment1->id,
            'location_id' => $location1->id,
            'sequence' => 1,
        ]);

        DeliveryStop::factory()->create([
            'delivery_round_id' => $this->round->id,
            'shipment_id' => $shipment2->id,
            'location_id' => $location2->id,
            'sequence' => 2,
        ]);

        $result = $this->service->optimizeRoute($this->round);

        expect($result['total_distance_km'])->toBeGreaterThan(0);
        expect($result['estimated_time_minutes'])->toBeGreaterThan(0);
    });
});
