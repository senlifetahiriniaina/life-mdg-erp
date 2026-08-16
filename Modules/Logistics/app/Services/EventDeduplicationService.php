<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;

/**
 * EventDeduplicationService guards against re-processing the same carrier
 * webhook tracking event twice (e.g. on webhook retries), using either the
 * carrier-provided event ID or a caller-supplied idempotency key.
 */
class EventDeduplicationService
{
    /**
     * Determine whether a tracking event has already been recorded for this
     * shipment, using the provider event ID and/or idempotency key when
     * available, falling back to an exact shipment+type+timestamp match.
     */
    public function isDuplicate(
        int $shipmentId,
        string $eventType,
        DateTimeInterface|string|null $recordedAt = null,
        ?string $providerEventId = null,
        ?string $idempotencyKey = null
    ): bool {
        if (! empty($providerEventId)) {
            $exists = TrackingEvent::query()
                ->where('shipment_id', $shipmentId)
                ->where('provider_event_id', $providerEventId)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        if (! empty($idempotencyKey)) {
            $exists = TrackingEvent::query()
                ->where('shipment_id', $shipmentId)
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        // No dedup identifier supplied at all: fall back to an exact
        // shipment + event_type + recorded_at match to avoid accidental
        // double-inserts from naive retries.
        if (empty($providerEventId) && empty($idempotencyKey) && $recordedAt !== null) {
            return TrackingEvent::query()
                ->where('shipment_id', $shipmentId)
                ->where('event_type', $eventType)
                ->where('recorded_at', $this->toCarbon($recordedAt))
                ->exists();
        }

        return false;
    }

    /**
     * Create a TrackingEvent for the given shipment unless it is a duplicate
     * or fails validation, in which case null is returned instead of
     * throwing so webhook handlers can simply skip/ack the delivery.
     */
    public function createEvent(Shipment $shipment, array $data): ?TrackingEvent
    {
        $errors = $this->validateEventData($data);

        if (! empty($errors)) {
            return null;
        }

        $eventType = (string) $data['event_type'];
        $recordedAt = $data['recorded_at'];
        $providerEventId = $data['provider_event_id'] ?? null;
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($this->isDuplicate($shipment->id, $eventType, $recordedAt, $providerEventId, $idempotencyKey)) {
            return null;
        }

        return $shipment->trackingEvents()->create($data);
    }

    /**
     * Validate the minimal shape of incoming tracking event data before it
     * is persisted. Returns an array of human-readable error messages
     * (empty array = valid).
     */
    public function validateEventData(array $data): array
    {
        $errors = [];

        if (empty($data['event_type'])) {
            $errors[] = 'event_type is required.';
        }

        if (empty($data['recorded_at'])) {
            $errors[] = 'recorded_at is required.';
        }

        if (array_key_exists('latitude', $data) && $data['latitude'] !== null && $data['latitude'] !== '') {
            $latitude = (float) $data['latitude'];
            if ($latitude < -90 || $latitude > 90) {
                $errors[] = 'latitude must be between -90 and 90.';
            }
        }

        if (array_key_exists('longitude', $data) && $data['longitude'] !== null && $data['longitude'] !== '') {
            $longitude = (float) $data['longitude'];
            if ($longitude < -180 || $longitude > 180) {
                $errors[] = 'longitude must be between -180 and 180.';
            }
        }

        return $errors;
    }

    /**
     * Purge deduplication metadata (provider event ID / idempotency key)
     * from tracking events older than the given retention window. The
     * events themselves are kept for audit/history purposes — only the
     * dedup keys, which are only needed to catch near-term webhook
     * retries, are cleared. Returns the number of rows affected.
     */
    public function cleanupOldDeduplicationData(int $olderThanDays = 30): int
    {
        $cutoff = Carbon::now()->subDays($olderThanDays);

        return TrackingEvent::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) {
                $query->whereNotNull('provider_event_id')
                    ->orWhereNotNull('idempotency_key');
            })
            ->update([
                'provider_event_id' => null,
                'idempotency_key' => null,
            ]);
    }

    private function toCarbon(DateTimeInterface|string $value): CarbonInterface
    {
        return $value instanceof CarbonInterface
            ? $value
            : Carbon::parse($value);
    }
}
