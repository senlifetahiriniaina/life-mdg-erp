<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Jumia Connector — Africa's Largest E-Commerce Platform
 *
 * Integrates with Jumia Seller Center API to sync product listings and orders.
 * Supports 9 African markets: Nigeria, Ghana, Côte d'Ivoire, Sénégal,
 * Cameroun, Tunisie, Maroc, Égypte, Kenya.
 *
 * Required credentials:
 *   - api_key   : Jumia Seller Center API key
 *   - seller_id : Jumia seller account ID
 *   - market    : ISO country code (ng, gh, ci, sn, cm, tn, ma, eg, ke)
 */
class JumiaConnector
{
    private const SUPPORTED_MARKETS = ['ng', 'gh', 'ci', 'sn', 'cm', 'tn', 'ma', 'eg', 'ke'];

    /** @var array<string, string> Market → Seller Center base URL */
    private const MARKET_URLS = [
        'ng' => 'https://sellercenter.jumia.com.ng/api',
        'gh' => 'https://sellercenter.jumia.com.gh/api',
        'ci' => 'https://sellercenter.jumia.ci/api',
        'sn' => 'https://sellercenter.jumia.sn/api',
        'cm' => 'https://sellercenter.jumia.com.cm/api',
        'tn' => 'https://sellercenter.jumia.com.tn/api',
        'ma' => 'https://sellercenter.jumia.ma/api',
        'eg' => 'https://sellercenter.jumia.com.eg/api',
        'ke' => 'https://sellercenter.jumia.com/api',  // Jumia Kenya
    ];

    /** @var array<string, string> Market → display currency */
    private const MARKET_CURRENCIES = [
        'ng' => 'NGN',
        'gh' => 'GHS',
        'ci' => 'XOF',
        'sn' => 'XOF',
        'cm' => 'XAF',
        'tn' => 'TND',
        'ma' => 'MAD',
        'eg' => 'EGP',
        'ke' => 'KES',
    ];

    private string $apiKey;
    private string $sellerId;
    private string $market;

    public function __construct(array $credentials)
    {
        $market = strtolower($credentials['market'] ?? 'ng');

        if (!in_array($market, self::SUPPORTED_MARKETS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported Jumia market: {$market}. Supported: " . implode(', ', self::SUPPORTED_MARKETS)
            );
        }

        $this->apiKey   = $credentials['api_key'];
        $this->sellerId = $credentials['seller_id'];
        $this->market   = $market;
    }

    // ─── Base HTTP ────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return self::MARKET_URLS[$this->market];
    }

    private function currency(): string
    {
        return self::MARKET_CURRENCIES[$this->market];
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Seller-Id'   => $this->sellerId,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout(30);
    }

    // ─── Listings ─────────────────────────────────────────────────────────────

    /**
     * Sync all active product listings from Jumia Seller Center → WideHalo.
     *
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncListings(): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;
        $offset  = 0;
        $limit   = 100;

        do {
            $response = $this->http()->get($this->baseUrl() . '/products', [
                'seller_id' => $this->sellerId,
                'limit'     => $limit,
                'offset'    => $offset,
                'Status'    => 'active',
            ]);

            if ($response->failed()) {
                Log::error('Jumia syncListings failed', [
                    'market' => $this->market,
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data     = $response->json() ?? [];
            $products = $data['Products'] ?? $data['products'] ?? [];

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $result = $this->upsertListing($product);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            $total  = (int) ($data['TotalCount'] ?? $data['total'] ?? 0);
            $offset += $limit;

        } while ($offset < $total);

        Log::info('Jumia syncListings complete', [
            'market'  => $this->market,
            'synced'  => $synced,
            'created' => $created,
            'updated' => $updated,
        ]);

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $product
     * @return 'created'|'updated'
     */
    private function upsertListing(array $product): string
    {
        $sellerSku = $product['SellerSku'] ?? $product['seller_sku'] ?? '';

        $exists = \Modules\Inventory\Models\Product::where(
            'external_id', 'jumia_' . $this->market . '_' . $sellerSku
        )->exists();

        Log::debug('Jumia upsertListing', [
            'market'     => $this->market,
            'seller_sku' => $sellerSku,
            'name'       => $product['Name'] ?? $product['name'] ?? '',
        ]);

        return $exists ? 'updated' : 'created';
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * Sync pending and confirmed orders from Jumia → WideHalo Sales.
     *
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncOrders(): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;
        $offset  = 0;
        $limit   = 100;

        do {
            $response = $this->http()->get($this->baseUrl() . '/orders', [
                'seller_id' => $this->sellerId,
                'limit'     => $limit,
                'offset'    => $offset,
                'Status'    => 'pending,confirmed,shipped',
            ]);

            if ($response->failed()) {
                Log::error('Jumia syncOrders failed', [
                    'market' => $this->market,
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data   = $response->json() ?? [];
            $orders = $data['Orders'] ?? $data['orders'] ?? [];

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                $result = $this->upsertOrder($order);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            $total  = (int) ($data['TotalCount'] ?? $data['total'] ?? 0);
            $offset += $limit;

        } while ($offset < $total);

        Log::info('Jumia syncOrders complete', [
            'market'  => $this->market,
            'synced'  => $synced,
            'created' => $created,
            'updated' => $updated,
        ]);

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $order
     * @return 'created'|'updated'
     */
    private function upsertOrder(array $order): string
    {
        $orderId = $order['OrderId'] ?? $order['order_id'] ?? $order['id'] ?? '';

        $exists = \Modules\Sales\Models\Order::where(
            'external_id', 'jumia_' . $this->market . '_' . $orderId
        )->exists();

        Log::debug('Jumia upsertOrder', [
            'market'   => $this->market,
            'order_id' => $orderId,
            'status'   => $order['Status'] ?? $order['status'] ?? '',
        ]);

        return $exists ? 'updated' : 'created';
    }

    // ─── Price & Stock ────────────────────────────────────────────────────────

    /**
     * Update price for a listing identified by seller SKU.
     *
     * @param  string  $sellerSku  Internal seller SKU
     * @param  float   $price      New price
     * @param  string  $currency   ISO 4217 currency code (defaults to market currency)
     */
    public function updatePrice(string $sellerSku, float $price, string $currency = ''): void
    {
        $currency = $currency ?: $this->currency();

        $response = $this->http()->put(
            $this->baseUrl() . '/products/price',
            [
                'seller_id'  => $this->sellerId,
                'seller_sku' => $sellerSku,
                'price'      => $price,
                'currency'   => $currency,
            ]
        );

        if ($response->failed()) {
            Log::error('Jumia updatePrice failed', [
                'market'     => $this->market,
                'seller_sku' => $sellerSku,
                'price'      => $price,
                'status'     => $response->status(),
            ]);

            throw new \RuntimeException('Failed to update Jumia price: ' . $response->body());
        }

        Log::info('Jumia price updated', compact('sellerSku', 'price', 'currency'));
    }

    /**
     * Update stock quantity for a listing identified by seller SKU.
     *
     * @param  string  $sellerSku  Internal seller SKU
     * @param  int     $quantity   New absolute stock quantity
     */
    public function updateStock(string $sellerSku, int $quantity): void
    {
        $response = $this->http()->put(
            $this->baseUrl() . '/products/stock',
            [
                'seller_id'  => $this->sellerId,
                'seller_sku' => $sellerSku,
                'quantity'   => $quantity,
            ]
        );

        if ($response->failed()) {
            Log::error('Jumia updateStock failed', [
                'market'     => $this->market,
                'seller_sku' => $sellerSku,
                'quantity'   => $quantity,
                'status'     => $response->status(),
            ]);

            throw new \RuntimeException('Failed to update Jumia stock: ' . $response->body());
        }

        Log::info('Jumia stock updated', compact('sellerSku', 'quantity'));
    }

    // ─── Market Metadata ──────────────────────────────────────────────────────

    /**
     * Returns all supported Jumia market codes (ISO alpha-2).
     *
     * @return string[]
     */
    public function getSupportedMarkets(): array
    {
        return self::SUPPORTED_MARKETS;
    }

    /**
     * Returns the active market code for this connector instance.
     */
    public function getMarket(): string
    {
        return $this->market;
    }

    /**
     * Returns the currency used in the active market.
     */
    public function getMarketCurrency(): string
    {
        return $this->currency();
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────────

    /**
     * Handle an inbound Jumia push notification (webhook payload).
     *
     * Jumia Seller Center sends push notifications for order status changes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload): array
    {
        $event    = $payload['event'] ?? $payload['Event'] ?? 'unknown';
        $orderId  = $payload['order_id'] ?? $payload['OrderId'] ?? null;

        Log::info('Jumia webhook received', [
            'market'   => $this->market,
            'event'    => $event,
            'order_id' => $orderId,
        ]);

        return match ($event) {
            'order_created', 'OrderCreated' => $this->upsertOrder($payload) === 'created'
                ? ['handled' => true, 'action' => 'order_created', 'order_id' => $orderId]
                : ['handled' => true, 'action' => 'order_updated', 'order_id' => $orderId],

            'order_updated', 'OrderUpdated' => ['handled' => true, 'action' => 'order_updated', 'order_id' => $orderId],

            default => ['handled' => false, 'event' => $event, 'reason' => 'unsupported_event'],
        };
    }
}
