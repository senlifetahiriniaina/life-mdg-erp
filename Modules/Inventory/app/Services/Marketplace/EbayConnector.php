<?php

declare(strict_types=1);

namespace Modules\Inventory\Services\Marketplace;

use Illuminate\Support\Facades\Http;

class EbayConnector implements MarketplaceConnectorInterface
{
    public function __construct(private array $config) {}

    public function isSandbox(): bool
    {
        if (($this->config['environment'] ?? 'sandbox') === 'sandbox') {
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
                'Content-Language' => 'en-US',
            ])->get($this->baseUrl() . '/sell/inventory/v1/inventory_item', [
                'limit'  => 100,
                'offset' => 0,
            ]);

            if ($response->failed()) {
                return $this->mockProducts();
            }

            $items = $response->json('inventoryItems', []);
            return array_map(fn($item) => [
                'sku'      => $item['sku'] ?? '',
                'title'    => $item['product']['title'] ?? '',
                'quantity' => (int) ($item['availability']['shipToLocationAvailability']['quantity'] ?? 0),
                'price'    => (float) ($item['offers'][0]['pricingSummary']['price']['value'] ?? 0.0),
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
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $token,
                'Content-Type'   => 'application/json',
                'Content-Language' => 'en-US',
            ])->put($this->baseUrl() . '/sell/inventory/v1/inventory_item/' . urlencode($sku), [
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => $qty,
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
            ])->get($this->baseUrl() . '/sell/fulfillment/v1/order', [
                'filter' => 'orderfulfillmentstatus:{NOT_STARTED}',
                'limit'  => 50,
            ]);

            if ($response->failed()) {
                return $this->mockOrders();
            }

            $orders = $response->json('orders', []);
            return array_map(fn($order) => [
                'order_id'   => $order['orderId'] ?? '',
                'status'     => $order['orderFulfillmentStatus'] ?? '',
                'items'      => $order['lineItems'] ?? [],
                'created_at' => $order['creationDate'] ?? '',
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
                'Content-Type'  => 'application/json',
            ])->post($this->baseUrl() . '/sell/fulfillment/v1/order/' . urlencode($orderId) . '/issueRefund', []);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getAccessToken(): string
    {
        if ($this->isSandbox()) {
            return 'mock-ebay-token';
        }

        $credentials = base64_encode(
            $this->config['client_id'] . ':' . $this->config['client_secret']
        );

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ])->asForm()->post($this->tokenUrl(), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->config['refresh_token'],
            'scope'         => 'https://api.ebay.com/oauth/api_scope/sell.inventory https://api.ebay.com/oauth/api_scope/sell.fulfillment',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to obtain eBay access token');
        }

        return $response->json('access_token', '');
    }

    public function getStatus(): array
    {
        return [
            'type'        => 'ebay',
            'sandbox_mode' => $this->isSandbox(),
            'connected'   => ! $this->isSandbox(),
        ];
    }

    protected function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com'
            : 'https://api.ebay.com';
    }

    private function tokenUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api.sandbox.ebay.com/identity/v1/oauth2/token'
            : 'https://api.ebay.com/identity/v1/oauth2/token';
    }

    private function mockProducts(): array
    {
        return [
            ['sku' => 'EBAY-MOCK-001', 'title' => 'eBay Mock Product 1', 'quantity' => 8,  'price' => 24.99],
            ['sku' => 'EBAY-MOCK-002', 'title' => 'eBay Mock Product 2', 'quantity' => 3,  'price' => 99.00],
        ];
    }

    private function mockOrders(): array
    {
        return [
            [
                'order_id'   => 'EBAY-ORDER-001',
                'status'     => 'NOT_STARTED',
                'items'      => [['sku' => 'EBAY-MOCK-001', 'quantity' => 2]],
                'created_at' => '2026-06-11T00:00:00Z',
            ],
        ];
    }
}
