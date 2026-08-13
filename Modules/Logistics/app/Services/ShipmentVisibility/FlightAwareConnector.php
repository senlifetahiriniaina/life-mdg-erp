<?php

declare(strict_types=1);

namespace Modules\Logistics\Services\ShipmentVisibility;

use Illuminate\Support\Facades\Http;

/**
 * FlightAware AeroAPI connector for air cargo tracking.
 */
class FlightAwareConnector
{
    private const BASE_URL = 'https://aeroapi.flightaware.com/aeroapi';

    public function __construct(private array $config = []) {}

    public function isSandbox(): bool
    {
        return empty($this->config['api_key']);
    }

    public function getFlightVisibility(string $flightNumber): ?array
    {
        if ($this->isSandbox()) {
            return $this->mockVisibility($flightNumber);
        }

        try {
            $response = Http::withHeaders([
                'x-apikey' => $this->config['api_key'],
            ])->get(self::BASE_URL . '/flights/' . $flightNumber, [
                'max_pages' => 1,
            ]);

            if ($response->failed()) {
                return $this->mockVisibility($flightNumber);
            }

            $flight = $response->json('flights.0', []);
            return $this->normalize($flight);
        } catch (\Throwable) {
            return $this->mockVisibility($flightNumber);
        }
    }

    private function normalize(array $f): array
    {
        return [
            'mode'          => 'air',
            'carrier'       => $f['operator'] ?? null,
            'flight_number' => $f['ident'] ?? null,
            'status'        => $f['status'] ?? 'unknown',
            'current_position' => [
                'lat' => $f['last_position']['latitude'] ?? null,
                'lng' => $f['last_position']['longitude'] ?? null,
            ],
            'eta'           => $f['estimated_in'] ?? null,
            'events'        => [
                ['timestamp' => $f['actual_out'] ?? null, 'location' => $f['origin']['code'] ?? null, 'description' => 'Departed'],
                ['timestamp' => $f['actual_in'] ?? null,  'location' => $f['destination']['code'] ?? null, 'description' => 'Arrived'],
            ],
        ];
    }

    private function mockVisibility(string $flightNumber): array
    {
        return [
            'mode'          => 'air',
            'carrier'       => 'Air France Cargo',
            'flight_number' => $flightNumber,
            'status'        => 'en_route',
            'current_position' => [
                'lat' => 48.8566,
                'lng' => 2.3522,
            ],
            'eta'           => now()->addHours(4)->toIso8601String(),
            'events'        => [
                ['timestamp' => now()->subHours(8)->toDateTimeString(), 'location' => 'DKR', 'description' => 'Departed Dakar'],
                ['timestamp' => now()->subHours(2)->toDateTimeString(), 'location' => 'CDG', 'description' => 'Arrived Paris CDG'],
            ],
            'sandbox'       => true,
        ];
    }
}
