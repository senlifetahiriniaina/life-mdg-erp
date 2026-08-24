<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;
use Modules\Logistics\Services\EventDeduplicationService;

/**
 * @group Controllers - Tracking Event
 *
 * Manage Tracking Event resources.
 */
class TrackingEventController extends Controller
{
    public function __construct(private readonly EventDeduplicationService $dedup) {}

    public function store(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $request->validate([
            'event_type' => 'required|in:booked,picked_up,in_transit,customs_clearance,out_for_delivery,delivered,exception,returned,cancelled',
            'status_detail' => 'nullable|string|max:255',
            'location_name' => 'nullable|string|max:200',
            'location_city' => 'nullable|string|max:100',
            'location_country' => 'nullable|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_exception' => 'nullable|boolean',
            'exception_reason' => 'nullable|string|max:255',
            'recorded_at' => 'nullable|date',
            // Chantier 32.23: EventDeduplicationService was fully written and
            // tested (webhook-retry-safe dedup via provider_event_id/
            // idempotency_key, both real, migrated columns on
            // logistics_tracking_events) but had zero real producer anywhere
            // — this, the one real endpoint that creates a tracking event,
            // never accepted either field, so a carrier webhook retry would
            // have always inserted a second duplicate row (and redundantly
            // re-applied the shipment status transition). Activated here.
            'provider_event_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $data['recorded_at'] = $data['recorded_at'] ?? now();

        if ($this->dedup->isDuplicate(
            $shipment->id,
            $data['event_type'],
            $data['recorded_at'],
            $data['provider_event_id'] ?? null,
            $data['idempotency_key'] ?? null,
        )) {
            return response()->json(['message' => 'Event already recorded (duplicate).'], 200);
        }

        $event = TrackingEvent::create(array_merge($data, [
            'shipment_id' => $shipment->id,
            'recorded_by' => $request->user()->id,
        ]));

        // Update shipment status based on event
        $statusMap = [
            'booked' => 'booked',
            'picked_up' => 'picked_up',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'returned' => 'returned',
            'cancelled' => 'cancelled',
        ];

        if (isset($statusMap[$data['event_type']]) && ! in_array($shipment->status, ['delivered', 'cancelled'])) {
            $shipment->update(['status' => $statusMap[$data['event_type']]]);
        }

        return response()->json($event, 201);
    }
}
