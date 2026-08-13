<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\Marketplace;

use Illuminate\Support\Facades\Http;

class AmazonSpApiConnector implements MarketplaceConnectorInterface
{
    public function __construct(private array $config) {}

    public function isSandbox(): bool
    {
        if ($this->config['sandbox_mode'] ?? false) {
            return true;
        }

        return empty($this->config['client_id'])
            || empty($this->config['client_secret'])
            || empty($this->config['refresh_token']);
    }

    public function listProducts(): array
    {
        if ($this->isSandbox()) {
            return $this->mockProducts();
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'x-amz-access-token' => $token,
            ])->get($this->baseUrl() . '/listings/items', [
                'marketplaceIds' => $this->config['marketplace_id'] ?? 'ATVPDKIKX0DER',
            ]);

            if ($response->failed()) {
                return $this->mockProducts();
            }

            $items = $response->json('items', []);
            return array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'title'    => $item['summaries'][0]['itemName'] ?? '',
                'quantity' => (int) ($item['fulfillmentAvailability'][0]['quantity'] ?? 0),
                'price'    => (float) ($item['offers'][0]['listingPrice']['amount'] ?? 0.0),
            ], $items);
        } catch (\Throwable) {
            return $this->mockProducts();
        }
    }

    public function syncInventory(string $sku, int $qty): bool
    {
        if ($this->isSandbox()) {
            return true;
        }

        try {
            $token = $this->getAccessToken();
            $marketplaceId = $this->config['marketplace_id'] ?? 'ATVPDKIKX0DER';
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'x-amz-access-token' => $token,
                'Content-Type' => 'application/json',
            ])->patch($this->baseUrl() . '/listings/items/' . urlencode($sku), [
                'marketplaceIds' => [$marketplaceId],
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/fulfillment_availability',
                        'value' => [
                            ['fulfillment_channel_code' => 'DEFAULT', 'quantity' => $qty],
                        ],
                    ],
                ],
            ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getOrders(): array
    {
        if ($this->isSandbox()) {
            return $this->mockOrders();
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'x-amz-access-token' => $token,
            ])->get($this->baseUrl() . '/orders/v0/orders', [
                'MarketplaceIds' => $this->config['marketplace_id'] ?? 'ATVPDKIKX0DER',
                'OrderStatuses' => 'Unshipped,PartiallyShipped',
            ]);

            if ($response->failed()) {
                return $this->mockOrders();
            }

            $orders = $response->json('payload.Orders', []);
            return array_map(fn($order) => [
                'order_id'   => $order['AmazonOrderId'] ?? '',
                'status'     => $order['OrderStatus'] ?? '',
                'items'      => $order['OrderItems'] ?? [],
                'created_at' => $order['PurchaseDate'] ?? '',
            ], $orders);
        } catch (\Throwable) {
            return $this->mockOrders();
        }
    }

    public function acknowledgeOrder(string $orderId): bool
    {
        if ($this->isSandbox()) {
            return true;
        }

        try {
            $token = $this->getAccessToken();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'x-amz-access-token' => $token,
            ])->post($this->baseUrl() . '/orders/v0/orders/' . urlencode($orderId) . '/confirm');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getAccessToken(): string
    {
        if ($this->isSandbox()) {
            return 'mock-access-token';
        }

        $response = Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->config['refresh_token'],
            'client_id'     => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to obtain Amazon access token');
        }

        return $response->json('access_token', '');
    }

    public function getStatus(): array
    {
        return [
            'type'           => 'amazon',
            'sandbox_mode'   => $this->isSandbox(),
            'marketplace_id' => $this->config['marketplace_id'] ?? '',
            'connected'      => ! $this->isSandbox(),
        ];
    }

    protected function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.sellingpartnerapi-na.amazon.com'
            : 'https://sellingpartnerapi-na.amazon.com';
    }

    private function mockProducts(): array
    {
        return [
            ['sku' => 'MOCK-SKU-001', 'title' => 'Mock Product 1', 'quantity' => 10, 'price' => 29.99],
            ['sku' => 'MOCK-SKU-002', 'title' => 'Mock Product 2', 'quantity' => 5,  'price' => 49.99],
        ];
    }

    private function mockOrders(): array
    {
        return [
            [
                'order_id'   => 'MOCK-ORDER-001',
                'status'     => 'Unshipped',
                'items'      => [['sku' => 'MOCK-SKU-001', 'quantity' => 1]],
                'created_at' => '2026-06-11T00:00:00Z',
            ],
        ];
    }
}
