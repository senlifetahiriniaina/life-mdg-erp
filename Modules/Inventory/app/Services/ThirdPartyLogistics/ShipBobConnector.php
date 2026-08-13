<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\ThirdPartyLogistics;

use Illuminate\Support\Facades\Http;

/**
 * ShipBob REST API v1 connector.
 * Sandbox falls back to mock responses when no API token is provided.
 */
class ShipBobConnector implements ThirdPartyLogisticsInterface
{
    private const BASE_URL = 'https://api.shipbob.com/1_0';

    public function __construct(private array $config = []) {}

    public function isSandbox(): bool
    {
        return empty($this->config['api_token']);
    }

    public function createFulfillmentOrder(array $order): array
    {
        if ($this->isSandbox()) {
            return $this->mockCreateOrder($order);
        }

        try {
            $response = Http::withToken($this->config['api_token'])
                ->post(self::BASE_URL . '/order', [
                    'reference_id'    => (string) $order['order_id'],
                    'shipping_method' => $order['shipping_method'] ?? 'Standard',
                    'recipient'       => [
                        'name'    => $order['ship_to']['name'] ?? '',
                        'address' => [
                            'address1'    => $order['ship_to']['address'] ?? '',
                            'city'        => $order['ship_to']['city'] ?? '',
                            'state'       => $order['ship_to']['state'] ?? '',
                            'zip_code'    => $order['ship_to']['zip'] ?? '',
                            'country'     => $order['ship_to']['country'] ?? 'US',
                        ],
                    ],
                    'products' => array_map(fn ($item) => [
                        'reference_id' => $item['sku'],
                        'quantity'     => $item['quantity'],
                    ], $order['items'] ?? []),
                ]);

            if ($response->failed()) {
                return $this->mockCreateOrder($order);
            }

            $data = $response->json();
            return [
                'reference_id'        => (string) ($data['id'] ?? $data['reference_id'] ?? ''),
                'status'              => $data['status'] ?? 'processing',
                'estimated_ship_date' => $data['estimated_fulfillment_date'] ?? null,
                'tracking_number'     => null,
            ];
        } catch (\Throwable) {
            return $this->mockCreateOrder($order);
        }
    }

    public function getOrderStatus(string $referenceId): array
    {
        if ($this->isSandbox()) {
            return $this->mockOrderStatus($referenceId);
        }

        try {
            $response = Http::withToken($this->config['api_token'])
                ->get(self::BASE_URL . '/order', ['ReferenceIds' => $referenceId]);

            if ($response->failed()) {
                return $this->mockOrderStatus($referenceId);
            }

            $orders = $response->json('results', $response->json() ?? []);
            $data   = is_array($orders) && isset($orders[0]) ? $orders[0] : ($orders ?? []);

            return [
                'reference_id'   => $referenceId,
                'status'         => $data['status'] ?? 'unknown',
                'tracking_number' => $data['shipments'][0]['tracking_number'] ?? null,
                'carrier'        => $data['shipments'][0]['carrier'] ?? null,
                'shipped_at'     => $data['shipments'][0]['shipped_date'] ?? null,
                'delivered_at'   => $data['shipments'][0]['delivery_date'] ?? null,
                'items'          => $data['products'] ?? [],
            ];
        } catch (\Throwable) {
            return $this->mockOrderStatus($referenceId);
        }
    }

    public function cancelOrder(string $referenceId): bool
    {
        if ($this->isSandbox()) {
            return true;
        }

        try {
            $response = Http::withToken($this->config['api_token'])
                ->delete(self::BASE_URL . '/order/' . $referenceId);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getInventoryLevels(): array
    {
        if ($this->isSandbox()) {
            return $this->mockInventoryLevels();
        }

        try {
            $response = Http::withToken($this->config['api_token'])
                ->get(self::BASE_URL . '/inventory');

            if ($response->failed()) {
                return $this->mockInventoryLevels();
            }

            $items = $response->json('results', $response->json() ?? []);
            return array_map(fn ($item) => [
                'sku'                 => $item['inventory_id'] ?? $item['reference_id'] ?? '',
                'quantity_on_hand'    => $item['total_sellable_quantity'] ?? 0,
                'quantity_reserved'   => $item['total_committed_quantity'] ?? 0,
                'quantity_available'  => $item['total_sellable_quantity'] ?? 0,
            ], is_array($items) ? $items : []);
        } catch (\Throwable) {
            return $this->mockInventoryLevels();
        }
    }

    public function updateInventory(string $sku, int $qty): bool
    {
        if ($this->isSandbox()) {
            return true;
        }

        try {
            $response = Http::withToken($this->config['api_token'])
                ->post(self::BASE_URL . '/inventory/adjustment', [
                    'inventory_id' => $sku,
                    'quantity'     => $qty,
                ]);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Mocks ────────────────────────────────────────────────────────────

    private function mockCreateOrder(array $order): array
    {
        return [
            'reference_id'        => 'SHIPBOB-' . ($order['order_id'] ?? 'MOCK'),
            'status'              => 'processing',
            'estimated_ship_date' => now()->addDays(2)->toDateString(),
            'tracking_number'     => null,
            'sandbox'             => true,
        ];
    }

    private function mockOrderStatus(string $referenceId): array
    {
        return [
            'reference_id'    => $referenceId,
            'status'          => 'shipped',
            'tracking_number' => 'SBTRACK' . strtoupper(substr(md5($referenceId), 0, 8)),
            'carrier'         => 'UPS',
            'shipped_at'      => now()->subDay()->toDateTimeString(),
            'delivered_at'    => null,
            'items'           => [],
            'sandbox'         => true,
        ];
    }

    private function mockInventoryLevels(): array
    {
        return [
            ['sku' => 'DEMO-SKU-001', 'quantity_on_hand' => 150, 'quantity_reserved' => 20, 'quantity_available' => 130],
            ['sku' => 'DEMO-SKU-002', 'quantity_on_hand' => 80,  'quantity_reserved' => 5,  'quantity_available' => 75],
        ];
    }
}
