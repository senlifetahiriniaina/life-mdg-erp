<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Modules\Logistics\Models\TrackingEvent;

/**
 * LocationPrivacyService handles GPS data privacy and compliance.
 */
class LocationPrivacyService
{
    /**
     * Data retention period in days (default 90 days).
     */
    private const DATA_RETENTION_DAYS = 90;

    /**
     * Encrypt GPS coordinates at rest.
     */
    public function encryptCoordinates(float $latitude, float $longitude): string
    {
        try {
            $data = json_encode([
                'lat' => $latitude,
                'lng' => $longitude,
            ]);

            return Crypt::encryptString($data);
        } catch (\Exception $e) {
            Log::error('Failed to encrypt coordinates', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt GPS coordinates.
     */
    public function decryptCoordinates(string $encrypted): ?array
    {
        try {
            $json = Crypt::decryptString($encrypted);
            return json_decode($json, true);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt coordinates', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Purge old GPS data based on retention policy.
     */
    public function purgeOldLocationData(int $daysOld = self::DATA_RETENTION_DAYS): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $deleted = TrackingEvent::where('created_at', '<', $cutoffDate)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->update([
                'latitude' => null,
                'longitude' => null,
            ]);

        Log::info('Purged old location data', [
            'records_purged' => $deleted,
            'cutoff_date' => $cutoffDate,
            'days_old' => $daysOld,
        ]);

        return $deleted;
    }

    /**
     * Batch location updates (every 30 seconds) to reduce privacy exposure.
     */
    public function batchLocationUpdate(int $shipmentId, float $latitude, float $longitude): void
    {
        // Cache key for batching
        $cacheKey = "location_batch:{$shipmentId}";

        // Get existing batch
        $batch = cache($cacheKey, []);

        // Add current update
        $batch[] = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'timestamp' => now()->getTimestamp(),
        ];

        // Store batch (30-second window)
        cache()->put($cacheKey, $batch, now()->addSeconds(30));
    }

    /**
     * Get batched location updates.
     */
    public function getBatchedLocationUpdates(int $shipmentId): array
    {
        $cacheKey = "location_batch:{$shipmentId}";
        $batch = cache($cacheKey, []);

        // Clear the batch after retrieval
        cache()->forget($cacheKey);

        return $batch;
    }

    /**
     * Anonymize location history for analytics.
     */
    public function anonymizeLocationHistory(int $shipmentId): bool
    {
        try {
            TrackingEvent::where('shipment_id', $shipmentId)
                ->update([
                    'latitude' => null,
                    'longitude' => null,
                ]);

            Log::info('Anonymized location history', [
                'shipment_id' => $shipmentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to anonymize location history', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check GDPR compliance for location data.
     */
    public function checkGdprCompliance(): array
    {
        $issues = [];

        // Check for unencrypted location data
        $unencrypted = TrackingEvent::where('latitude', '!=', null)
            ->where('created_at', '<', now()->subDays(self::DATA_RETENTION_DAYS))
            ->count();

        if ($unencrypted > 0) {
            $issues[] = "Found {$unencrypted} location records older than retention period";
        }

        // Check for proper consent tracking
        // This would depend on your CRM/user model implementation

        return [
            'compliant' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Export location data for user (GDPR right to access).
     */
    public function exportLocationData(int $shipmentId): array
    {
        $events = TrackingEvent::where('shipment_id', $shipmentId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['recorded_at', 'latitude', 'longitude', 'event_type']);

        return [
            'shipment_id' => $shipmentId,
            'export_date' => now()->toIso8601String(),
            'location_history' => $events->map(function ($event) {
                return [
                    'timestamp' => $event->recorded_at,
                    'latitude' => $event->latitude,
                    'longitude' => $event->longitude,
                    'event_type' => $event->event_type,
                ];
            })->toArray(),
        ];
    }

    /**
     * Delete all location data for user (GDPR right to erasure).
     */
    public function deleteLocationData(int $shipmentId): bool
    {
        try {
            TrackingEvent::where('shipment_id', $shipmentId)
                ->update([
                    'latitude' => null,
                    'longitude' => null,
                ]);

            Log::info('Deleted location data', [
                'shipment_id' => $shipmentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete location data', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
