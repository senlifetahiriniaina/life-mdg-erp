<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Http;

/**
 * FlightAware AeroAPI connector for air freight visibility.
 *
 * API: https://flightaware.com/aeroapi/
 */
class FlightAwareConnector
{
    public function __construct(
        private string $apiKey = '',
        private string $baseUrl = 'https://aeroapi.flightaware.com/aeroapi',
    ) {
        $this->apiKey  = config('services.flightaware.key', '');
        $this->baseUrl = config('services.flightaware.url', $this->baseUrl);
    }

    /**
     * Track an air cargo flight by flight number (e.g. "AF6543").
     *
     * @return array{mode: string, position: array|null, eta: string|null, events: array, source: string}
     */
    public function track(string $flightNumber): array
    {
        if (empty($this->apiKey) || empty($flightNumber)) {
            return $this->sandboxResponse($flightNumber);
        }

        try {
            $response = Http::withHeaders(['x-apikey' => $this->apiKey])
                ->timeout(10)
                ->get("{$this->baseUrl}/flights/{$flightNumber}");

            if ($response->failed()) {
                return $this->sandboxResponse($flightNumber);
            }

            $flight = $response->json('flights.0', []);

            return [
                'mode'     => 'air',
                'position' => isset($flight['last_position']) ? [
                    'lat' => (float) ($flight['last_position']['latitude'] ?? 0),
                    'lon' => (float) ($flight['last_position']['longitude'] ?? 0),
                ] : null,
                'eta'      => $flight['estimated_on'] ?? $flight['scheduled_on'] ?? null,
                'flight'   => [
                    'number'  => $flightNumber,
                    'origin'  => $flight['origin']['code_iata'] ?? '',
                    'dest'    => $flight['destination']['code_iata'] ?? '',
                    'status'  => $flight['status'] ?? '',
                ],
                'events'   => [],
                'source'   => 'flightaware',
            ];
        } catch (\Throwable) {
            return $this->sandboxResponse($flightNumber);
        }
    }

    private function sandboxResponse(string $flightNumber): array
    {
        return [
            'mode'     => 'air',
            'position' => ['lat' => 5.6037, 'lon' => -0.1870], // Accra airport
            'eta'      => now()->addHours(6)->toDateTimeString(),
            'flight'   => [
                'number' => $flightNumber ?: 'AF6543',
                'origin' => 'CDG',
                'dest'   => 'ACC',
                'status' => 'en_route',
            ],
            'events'   => [
                ['status' => 'departed', 'location' => 'Paris CDG', 'timestamp' => now()->subHours(8)->toDateTimeString()],
            ],
            'source'   => 'sandbox',
        ];
    }
}
