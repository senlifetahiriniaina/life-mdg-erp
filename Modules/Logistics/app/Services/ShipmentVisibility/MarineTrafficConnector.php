<?php

declare(strict_types=1);

namespace Modules\Logistics\Services\ShipmentVisibility;

use Illuminate\Support\Facades\Http;

/**
 * MarineTraffic AIS vessel tracking connector.
 * Sandbox when no API key provided.
 */
class MarineTrafficConnector
{
    private const BASE_URL = 'https://services.marinetraffic.com/api';

    public function __construct(private array $config = []) {}

    public function isSandbox(): bool
    {
        return empty($this->config['api_key']);
    }

    /**
     * Get vessel position by MMSI or vessel name.
     */
    public function getVesselPosition(string $vesselId): ?array
    {
        if ($this->isSandbox()) {
            return $this->mockVesselPosition($vesselId);
        }

        try {
            $response = Http::get(self::BASE_URL . '/exportvessel/v:5/' . $this->config['api_key'], [
                'mmsi'     => $vesselId,
                'protocol' => 'jsono',
            ]);

            if ($response->failed()) {
                return $this->mockVesselPosition($vesselId);
            }

            $data = $response->json();
            return isset($data[0]) ? $this->normalizeVessel($data[0]) : null;
        } catch (\Throwable) {
            return $this->mockVesselPosition($vesselId);
        }
    }

    private function normalizeVessel(array $raw): array
    {
        return [
            'vessel_name' => $raw['SHIPNAME'] ?? 'Unknown',
            'mmsi'        => $raw['MMSI'] ?? null,
            'lat'         => (float) ($raw['LAT'] ?? 0),
            'lng'         => (float) ($raw['LON'] ?? 0),
            'speed'       => (float) ($raw['SPEED'] ?? 0),
            'status'      => $raw['STATUS'] ?? 'At sea',
            'eta'         => $raw['ETA'] ?? null,
            'destination' => $raw['DESTINATION'] ?? null,
        ];
    }

    private function mockVesselPosition(string $vesselId): array
    {
        return [
            'vessel_name' => 'MV WIDEHALO EXPRESS',
            'mmsi'        => $vesselId,
            'lat'         => 14.693425,   // Dakar area
            'lng'         => -17.447938,
            'speed'       => 12.5,
            'status'      => 'Under way',
            'eta'         => now()->addDays(8)->toDateString(),
            'destination' => 'FRLEH',     // Le Havre
            'sandbox'     => true,
        ];
    }
}
