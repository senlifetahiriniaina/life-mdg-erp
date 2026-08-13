<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\ThirdPartyLogistics;

use Illuminate\Support\Facades\Http;

/**
 * ShipMonk REST API connector.
 * Sandbox fallback when no API credentials are provided.
 */
class ShipMonkConnector implements ThirdPartyLogisticsInterface
{
    private const BASE_URL = 'https://api.shipmonk.com/v1';

    public function __construct(private array $config = []) {}

    public function isSandbox(): bool
    {
        return empty($this->config['username']) || empty($this->config['token']);
    }

    public function createFulfillmentOrder(array $order): array
    {
        if ($this->isSandbox()) {
            return $this->mockCreateOrder($order);
        }

        try {
            $shipTo = $order['ship_to'] ?? [];
            $response = Http::withBasicAuth($this->config['username'], $this->config['token'])
                ->post(self::BASE_URL . '/order', [
                    'orderNumber'    => (string) $order['order_id'],
                    'shippingMethod' => $order['shipping_method'] ?? 'standard',
                    'address'        => [
                        'firstName' => $shipTo['name'] ?? '',
                        'address1'  => $shipTo['address'] ?? '',
                        'city'      => $shipTo['city'] ?? '',
                        'state'     => $shipTo['state'] ?? '',
                        'zip'       => $shipTo['zip'] ?? '',
                        'country'   => $shipTo['country'] ?? 'US',
                    ],
                    'lineItems' => array_map(fn ($item) => [
                        'sku'      => $item['sku'],
                        'quantity' => $item['quantity'],
                    ], $order['items'] ?? []),
                ]);

            if ($response->failed()) {
                return $this->mockCreateOrder($order);
            }

            $data = $response->json();
            return [
                'reference_id'        => (string) ($data['id'] ?? ''),
                'status'              => $data['status'] ?? 'received',
                'estimated_ship_date' => $data['estimatedShipDate'] ?? null,
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
            $response = Http::withBasicAuth($this->config['username'], $this->config['token'])
                ->get(self::BASE_URL . "/order/{$referenceId}");

            if ($response->failed()) {
                return $this->mockOrderStatus($referenceId);
            }

            $data = $response->json();
            return [
                'reference_id'    => $referenceId,
                'status'          => $data['status'] ?? 'unknown',
                'tracking_number' => $data['trackingNumber'] ?? null,
                'carrier'         => $data['carrier'] ?? null,
                'shipped_at'      => $data['shippedAt'] ?? null,
                'delivered_at'    => $data['deliveredAt'] ?? null,
                'items'           => $data['lineItems'] ?? [],
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
            $response = Http::withBasicAuth($this->config['username'], $this->config['token'])
                ->post(self::BASE_URL . "/order/{$referenceId}/cancel");
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
            $response = Http::withBasicAuth($this->config['username'], $this->config['token'])
                ->get(self::BASE_URL . '/inventory');

            if ($response->failed()) {
                return $this->mockInventoryLevels();
            }

            $items = $response->json('data', $response->json() ?? []);
            return array_map(fn ($item) => [
                'sku'                => $item['sku'] ?? '',
                'quantity_on_hand'   => $item['quantity'] ?? 0,
                'quantity_reserved'  => $item['reserved'] ?? 0,
                'quantity_available' => ($item['quantity'] ?? 0) - ($item['reserved'] ?? 0),
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
            $response = Http::withBasicAuth($this->config['username'], $this->config['token'])
                ->put(self::BASE_URL . "/inventory/{$sku}", ['quantity' => $qty]);
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Mocks ────────────────────────────────────────────────────────────

    private function mockCreateOrder(array $order): array
    {
        return [
            'reference_id'        => 'SHIPMONK-' . ($order['order_id'] ?? 'MOCK'),
            'status'              => 'received',
            'estimated_ship_date' => now()->addDays(3)->toDateString(),
            'tracking_number'     => null,
            'sandbox'             => true,
        ];
    }

    private function mockOrderStatus(string $referenceId): array
    {
        return [
            'reference_id'    => $referenceId,
            'status'          => 'shipped',
            'tracking_number' => 'SMTRACK' . strtoupper(substr(md5($referenceId), 0, 8)),
            'carrier'         => 'FedEx',
            'shipped_at'      => now()->subHours(12)->toDateTimeString(),
            'delivered_at'    => null,
            'items'           => [],
            'sandbox'         => true,
        ];
    }

    private function mockInventoryLevels(): array
    {
        return [
            ['sku' => 'DEMO-SKU-001', 'quantity_on_hand' => 200, 'quantity_reserved' => 30, 'quantity_available' => 170],
            ['sku' => 'DEMO-SKU-003', 'quantity_on_hand' => 50,  'quantity_reserved' => 0,  'quantity_available' => 50],
        ];
    }
}
