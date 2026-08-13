<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Http;

/**
 * MarineTraffic vessel tracking connector (ocean visibility).
 *
 * API: https://www.marinetraffic.com/en/ais/details/ships/
 * Falls back to sandbox data when API key is absent.
 */
class MarineTrafficConnector
{
    public function __construct(
        private string $apiKey = '',
        private string $baseUrl = 'https://services.marinetraffic.com/api',
    ) {
        $this->apiKey  = config('services.marinetraffic.key', '');
        $this->baseUrl = config('services.marinetraffic.url', $this->baseUrl);
    }

    /**
     * Track a vessel by IMO number or MMSI.
     *
     * @return array{mode: string, position: array|null, eta: string|null, events: array, source: string}
     */
    public function track(string $identifier): array
    {
        if (empty($this->apiKey)) {
            return $this->sandboxResponse($identifier);
        }

        try {
            $response = Http::timeout(10)
                ->get("{$this->baseUrl}/getexpectedarrivals/v:3", [
                    'v'      => '3',
                    'apikey' => $this->apiKey,
                    'imo'    => $identifier,
                    'msgtype' => 'simple',
                ]);

            if ($response->failed()) {
                return $this->sandboxResponse($identifier);
            }

            $data = $response->json();

            return [
                'mode'     => 'ocean',
                'position' => [
                    'lat' => (float) ($data[0]['LAT'] ?? 0),
                    'lon' => (float) ($data[0]['LON'] ?? 0),
                ],
                'eta'      => $data[0]['ETA'] ?? null,
                'vessel'   => [
                    'name'  => $data[0]['SHIPNAME'] ?? $identifier,
                    'flag'  => $data[0]['FLAG'] ?? '',
                    'speed' => (float) ($data[0]['SPEED'] ?? 0),
                ],
                'events'   => [],
                'source'   => 'marinetraffic',
            ];
        } catch (\Throwable) {
            return $this->sandboxResponse($identifier);
        }
    }

    private function sandboxResponse(string $identifier): array
    {
        return [
            'mode'     => 'ocean',
            'position' => ['lat' => 14.7167, 'lon' => -17.4677], // Dakar port
            'eta'      => now()->addDays(7)->toDateTimeString(),
            'vessel'   => ['name' => "Vessel {$identifier}", 'flag' => 'SN', 'speed' => 12.5],
            'events'   => [
                ['status' => 'departed', 'location' => 'Dakar', 'timestamp' => now()->subDays(3)->toDateTimeString()],
            ],
            'source'   => 'sandbox',
        ];
    }
}
