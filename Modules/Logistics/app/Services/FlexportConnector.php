<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Facades\Http;

/**
 * Flexport cargo tracking connector (multi-modal).
 */
class FlexportConnector
{
    public function __construct(
        private string $apiKey = '',
        private string $baseUrl = 'https://api.flexport.com',
    ) {
        $this->apiKey  = config('services.flexport.key', '');
        $this->baseUrl = config('services.flexport.url', $this->baseUrl);
    }

    /**
     * @return array{mode: string, position: array|null, eta: string|null, events: array, source: string}
     */
    public function track(string $reference): array
    {
        if (empty($this->apiKey)) {
            return $this->sandboxResponse($reference);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/shipments", ['reference_id' => $reference]);

            if ($response->failed()) {
                return $this->sandboxResponse($reference);
            }

            $data = $response->json('data.0', []);

            return [
                'mode'     => $data['transportation_mode'] ?? 'ocean',
                'position' => null,
                'eta'      => $data['estimated_arrival_date'] ?? null,
                'events'   => array_map(fn ($e) => [
                    'status'    => $e['event_type'] ?? '',
                    'location'  => $e['location']['name'] ?? '',
                    'timestamp' => $e['occurred_at'] ?? '',
                ], $data['milestones'] ?? []),
                'source'   => 'flexport',
            ];
        } catch (\Throwable) {
            return $this->sandboxResponse($reference);
        }
    }

    private function sandboxResponse(string $reference): array
    {
        return [
            'mode'     => 'ocean',
            'position' => null,
            'eta'      => now()->addDays(10)->toDateString(),
            'events'   => [
                ['status' => 'booking_confirmed', 'location' => 'Abidjan', 'timestamp' => now()->subDays(5)->toDateTimeString()],
                ['status' => 'departed_origin',   'location' => 'Abidjan', 'timestamp' => now()->subDays(2)->toDateTimeString()],
            ],
            'source'   => 'sandbox',
        ];
    }
}
