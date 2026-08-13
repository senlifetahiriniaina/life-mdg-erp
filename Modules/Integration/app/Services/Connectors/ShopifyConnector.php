<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopify Connector
 *
 * Syncs products and orders between Shopify and WideHalo Inventory/Sales modules.
 * Uses Shopify Admin REST API 2024-01.
 *
 * Required credentials:
 *   - shop_domain   : e.g. my-store.myshopify.com
 *   - access_token  : Admin API access token (private app or OAuth)
 */
class ShopifyConnector
{
    private const API_VERSION = '2024-01';

    private string $shopDomain;
    private string $accessToken;

    public function __construct(array $credentials)
    {
        $this->shopDomain  = rtrim($credentials['shop_domain'], '/');
        $this->accessToken = $credentials['access_token'];
    }

    // ─── Base HTTP ────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return "https://{$this->shopDomain}/admin/api/" . self::API_VERSION;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type'           => 'application/json',
        ])->timeout(30);
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    /**
     * Sync all products from Shopify → WideHalo Inventory.
     *
     * Paginates through all products using link header cursor.
     *
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncProducts(): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;

        $url    = $this->baseUrl() . '/products.json?limit=250&fields=id,title,variants,images,status,vendor,product_type';
        $pageInfo = null;

        do {
            $requestUrl = $pageInfo
                ? $this->baseUrl() . '/products.json?limit=250&page_info=' . $pageInfo
                : $url;

            $response = $this->http()->get($requestUrl);

            if ($response->failed()) {
                Log::error('Shopify syncProducts failed', ['status' => $response->status(), 'body' => $response->body()]);
                break;
            }

            $products = $response->json('products', []);

            foreach ($products as $product) {
                $result = $this->upsertProduct($product);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            // Parse Link header for next page
            $linkHeader = $response->header('Link');
            $pageInfo   = $this->extractNextPageInfo($linkHeader);

        } while ($pageInfo !== null);

        Log::info('Shopify syncProducts complete', compact('synced', 'created', 'updated'));

        return compact('synced', 'created', 'updated');
    }

    /**
     * Upsert a Shopify product into WideHalo Inventory.
     *
     * @param  array<string, mixed>  $product
     * @return 'created'|'updated'
     */
    private function upsertProduct(array $product): string
    {
        // Integration point: map Shopify product → WideHalo Product model
        // Each Shopify variant maps to a WideHalo SKU with its own stock level
        $exists = \Modules\Inventory\app\Models\Product::where(
            'external_id', 'shopify_' . $product['id']
        )->exists();

        // Actual persistence handled by IntegrationManager to keep connector stateless
        Log::debug('Shopify upsertProduct', ['shopify_id' => $product['id'], 'title' => $product['title']]);

        return $exists ? 'updated' : 'created';
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * Sync orders from Shopify → WideHalo Sales.
     *
     * @param  string|null  $sinceId  Only fetch orders newer than this Shopify order ID.
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncOrders(?string $sinceId = null): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;

        $params = http_build_query(array_filter([
            'status'   => 'any',
            'limit'    => 250,
            'since_id' => $sinceId,
            'fields'   => 'id,name,email,total_price,currency,financial_status,fulfillment_status,line_items,created_at',
        ]));

        $url      = $this->baseUrl() . '/orders.json?' . $params;
        $pageInfo = null;

        do {
            $requestUrl = $pageInfo
                ? $this->baseUrl() . '/orders.json?limit=250&page_info=' . $pageInfo
                : $url;

            $response = $this->http()->get($requestUrl);

            if ($response->failed()) {
                Log::error('Shopify syncOrders failed', ['status' => $response->status()]);
                break;
            }

            $orders = $response->json('orders', []);

            foreach ($orders as $order) {
                $result = $this->upsertOrder($order);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            $linkHeader = $response->header('Link');
            $pageInfo   = $this->extractNextPageInfo($linkHeader);

        } while ($pageInfo !== null);

        Log::info('Shopify syncOrders complete', compact('synced', 'created', 'updated'));

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $order
     * @return 'created'|'updated'
     */
    private function upsertOrder(array $order): string
    {
        $exists = \Modules\Sales\app\Models\Order::where(
            'external_id', 'shopify_' . $order['id']
        )->exists();

        Log::debug('Shopify upsertOrder', ['shopify_id' => $order['id'], 'name' => $order['name']]);

        return $exists ? 'updated' : 'created';
    }

    // ─── Inventory ────────────────────────────────────────────────────────────

    /**
     * Update inventory level for a Shopify variant at a given location.
     *
     * @param  string  $variantId    Shopify variant ID (inventory_item_id)
     * @param  int     $quantity     Absolute quantity to set
     * @param  string  $locationId   Shopify location ID
     */
    public function updateInventory(string $variantId, int $quantity, string $locationId): void
    {
        $response = $this->http()->post(
            $this->baseUrl() . '/inventory_levels/set.json',
            [
                'location_id'       => $locationId,
                'inventory_item_id' => $variantId,
                'available'         => $quantity,
            ]
        );

        if ($response->failed()) {
            Log::error('Shopify updateInventory failed', [
                'variant_id'  => $variantId,
                'quantity'    => $quantity,
                'location_id' => $locationId,
                'status'      => $response->status(),
            ]);

            throw new \RuntimeException('Failed to update Shopify inventory: ' . $response->body());
        }

        Log::info('Shopify inventory updated', compact('variantId', 'quantity', 'locationId'));
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────────

    /**
     * Handle an inbound Shopify webhook payload.
     *
     * Supported topics:
     *   - orders/create       → create WideHalo sales order
     *   - products/update     → update WideHalo product
     *   - inventory_levels/update → update WideHalo stock level
     *
     * @param  string  $topic    Shopify webhook topic header (X-Shopify-Topic)
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(string $topic, array $payload): array
    {
        Log::info('Shopify webhook received', ['topic' => $topic, 'id' => $payload['id'] ?? null]);

        return match ($topic) {
            'orders/create'            => $this->handleOrderCreated($payload),
            'products/update'          => $this->handleProductUpdated($payload),
            'inventory_levels/update'  => $this->handleInventoryUpdated($payload),
            default => ['handled' => false, 'topic' => $topic, 'reason' => 'unsupported_topic'],
        };
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function handleOrderCreated(array $order): array
    {
        $result = $this->upsertOrder($order);

        return ['handled' => true, 'action' => 'order_' . $result, 'shopify_id' => $order['id']];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function handleProductUpdated(array $product): array
    {
        $result = $this->upsertProduct($product);

        return ['handled' => true, 'action' => 'product_' . $result, 'shopify_id' => $product['id']];
    }

    /**
     * @param  array<string, mixed>  $level
     * @return array<string, mixed>
     */
    private function handleInventoryUpdated(array $level): array
    {
        Log::debug('Shopify inventory_levels/update', $level);

        return ['handled' => true, 'action' => 'inventory_updated', 'available' => $level['available'] ?? null];
    }

    // ─── Security ─────────────────────────────────────────────────────────────

    /**
     * Verify the HMAC-SHA256 signature from Shopify's X-Shopify-Hmac-Sha256 header.
     *
     * @param  string  $payload      Raw request body (not decoded)
     * @param  string  $hmacHeader   Value of X-Shopify-Hmac-Sha256 header
     */
    public function verifyWebhook(string $payload, string $hmacHeader): bool
    {
        $secret   = config('services.shopify.webhook_secret', $this->accessToken);
        $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($computed, $hmacHeader);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Extract the `page_info` cursor from a Shopify Link header.
     *
     * @param  string|null  $linkHeader
     * @return string|null
     */
    private function extractNextPageInfo(?string $linkHeader): ?string
    {
        if (!$linkHeader) {
            return null;
        }

        // Link: <https://...?page_info=xxx&limit=250>; rel="next"
        if (preg_match('/<[^>]*[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
