<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Logistics\Models\ShipmentTrackingEvent;
use Modules\Logistics\Services\ShipmentVisibility\FallbackTrackingConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlexportConnector;
use Modules\Logistics\Services\ShipmentVisibility\FlightAwareConnector;
use Modules\Logistics\Services\ShipmentVisibility\MarineTrafficConnector;

/**
 * ShipmentVisibilityService — aggregates multi-mode tracking data.
 *
 * Provider selection by transport_mode:
 *   ocean/sea → MarineTraffic (AIS) + Flexport
 *   air       → FlightAware
 *   *         → FallbackTrackingConnector (carrier slug → URL template)
 *
 * @method array getVisibility(array $shipment)
 * @method array refreshTracking(array $shipment)
 */
class ShipmentVisibilityService
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly MarineTrafficConnector  $marineTraffic,
        private readonly FlexportConnector       $flexport,
        private readonly FlightAwareConnector    $flightAware,
        private readonly FallbackTrackingConnector $fallbackConnector,
    ) {}

    /**
     * Get aggregated visibility for a shipment.
     *
     * @param  array $shipment {id, transport_mode, tracking_number, carrier_slug, mmsi, flight_number, flexport_id}
     * @return array {mode, carrier, vessel_name|flight_number, current_position{lat,lng}, status, eta, events[]}
     */
    public function getVisibility(array $shipment): array
    {
        $cacheKey = "shipvis:{$shipment['id']}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($shipment) {
            return $this->resolveVisibility($shipment);
        });
    }

    /**
     * Force-refresh tracking (bypasses cache) and return latest visibility.
     */
    public function refreshTracking(array $shipment): array
    {
        $visibility = $this->resolveVisibility($shipment);

        // Re-store in cache
        Cache::put("shipvis:{$shipment['id']}", $visibility, self::CACHE_TTL);

        return $visibility;
    }

    // ─── Private resolution ───────────────────────────────────────────────────

    private function resolveVisibility(array $shipment): array
    {
        $mode = strtolower($shipment['transport_mode'] ?? 'unknown');

        try {
            $visibility = match (true) {
                in_array($mode, ['sea', 'ocean', 'multimodal']) => $this->oceanVisibility($shipment),
                $mode === 'air'                                  => $this->airVisibility($shipment),
                default                                          => $this->fallbackConnector->getFallbackVisibility(
                    $shipment['tracking_number'] ?? '',
                    $shipment['carrier_slug'] ?? $mode
                ),
            };
        } catch (\Throwable $e) {
            Log::warning('ShipmentVisibilityService: resolution failed', [
                'shipment_id' => $shipment['id'] ?? null,
                'error'       => $e->getMessage(),
            ]);
            $visibility = $this->fallbackConnector->getFallbackVisibility(
                $shipment['tracking_number'] ?? '',
                $shipment['carrier_slug'] ?? 'unknown'
            );
        }

        // Persist new events to DB
        $this->persistEvents((int) ($shipment['id'] ?? 0), $mode, $visibility);

        return $visibility;
    }

    private function oceanVisibility(array $shipment): array
    {
        $mmsi       = $shipment['mmsi'] ?? $shipment['tracking_number'] ?? null;
        $flexportId = $shipment['flexport_id'] ?? $shipment['tracking_number'] ?? null;

        $vessel       = $mmsi ? $this->marineTraffic->getVesselPosition($mmsi) : null;
        $flexportData = $flexportId ? $this->flexport->getShipmentVisibility($flexportId) : null;

        if ($vessel && $flexportData) {
            return array_merge($flexportData, [
                'vessel_name'      => $vessel['vessel_name'],
                'current_position' => ['lat' => $vessel['lat'], 'lng' => $vessel['lng']],
                'speed'            => $vessel['speed'] ?? null,
            ]);
        }

        if ($vessel) {
            return [
                'mode'             => 'ocean',
                'carrier'          => $shipment['carrier'] ?? null,
                'vessel_name'      => $vessel['vessel_name'],
                'current_position' => ['lat' => $vessel['lat'], 'lng' => $vessel['lng']],
                'status'           => $vessel['status'] ?? 'in_transit',
                'eta'              => $vessel['eta'] ?? null,
                'events'           => [],
            ];
        }

        if ($flexportData) {
            return $flexportData;
        }

        return $this->fallbackConnector->getFallbackVisibility(
            $shipment['tracking_number'] ?? '',
            $shipment['carrier_slug'] ?? 'ocean'
        );
    }

    private function airVisibility(array $shipment): array
    {
        $flightNumber = $shipment['flight_number'] ?? $shipment['tracking_number'] ?? null;

        if ($flightNumber) {
            $flight = $this->flightAware->getFlightVisibility($flightNumber);
            if ($flight) {
                return $flight;
            }
        }

        return $this->fallbackConnector->getFallbackVisibility(
            $shipment['tracking_number'] ?? '',
            $shipment['carrier_slug'] ?? 'air'
        );
    }

    private function persistEvents(int $shipmentId, string $provider, array $visibility): void
    {
        if ($shipmentId === 0 || empty($visibility['events'])) {
            return;
        }

        foreach ($visibility['events'] as $event) {
            if (empty($event['timestamp'])) {
                continue;
            }

            try {
                ShipmentTrackingEvent::firstOrCreate(
                    [
                        'shipment_id' => $shipmentId,
                        'provider'    => $provider,
                        'event_type'  => $event['description'] ?? 'update',
                        'event_at'    => $event['timestamp'],
                    ],
                    [
                        'raw_payload' => $event,
                        'location'    => $event['location'] ?? null,
                    ]
                );
            } catch (\Throwable) {
                // Never fail on event persistence
            }
        }
    }
}
