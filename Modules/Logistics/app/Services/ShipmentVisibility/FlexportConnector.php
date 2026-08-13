<?php

declare(strict_types=1);

namespace Modules\Logistics\Services\ShipmentVisibility;

use Illuminate\Support\Facades\Http;

/**
 * Flexport ocean freight visibility connector.
 */
class FlexportConnector
{
    private const BASE_URL = 'https://api.flexport.com';

    public function __construct(private array $config = []) {}

    public function isSandbox(): bool
    {
        return empty($this->config['api_key']);
    }

    public function getShipmentVisibility(string $shipmentId): ?array
    {
        if ($this->isSandbox()) {
            return $this->mockVisibility($shipmentId);
        }

        try {
            $response = Http::withToken($this->config['api_key'])
                ->withHeaders(['Flexport-Version' => '2'])
                ->get(self::BASE_URL . '/shipments/' . $shipmentId);

            if ($response->failed()) {
                return $this->mockVisibility($shipmentId);
            }

            $data = $response->json('data', []);
            return $this->normalize($data);
        } catch (\Throwable) {
            return $this->mockVisibility($shipmentId);
        }
    }

    private function normalize(array $data): array
    {
        return [
            'mode'        => 'ocean',
            'carrier'     => $data['ocean_shipment']['carrier']['name'] ?? null,
            'vessel_name' => $data['ocean_shipment']['vessel']['name'] ?? null,
            'status'      => $data['status'] ?? 'unknown',
            'eta'         => $data['estimated_arrival_date'] ?? null,
            'events'      => array_map(fn ($e) => [
                'timestamp'   => $e['occurred_at'] ?? null,
                'location'    => $e['location']['unlocode'] ?? null,
                'description' => $e['event_type'] ?? null,
            ], $data['milestones'] ?? []),
        ];
    }

    private function mockVisibility(string $shipmentId): array
    {
        return [
            'mode'        => 'ocean',
            'carrier'     => 'Maersk',
            'vessel_name' => 'MAERSK DAKAR',
            'status'      => 'in_transit',
            'eta'         => now()->addDays(12)->toDateString(),
            'events'      => [
                ['timestamp' => now()->subDays(5)->toDateTimeString(), 'location' => 'SNDKR', 'description' => 'Departed origin port'],
                ['timestamp' => now()->subDays(2)->toDateTimeString(), 'location' => 'CNSHA', 'description' => 'Transshipment — Shanghai'],
            ],
            'sandbox'     => true,
        ];
    }
}
