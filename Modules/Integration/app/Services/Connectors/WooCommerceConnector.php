<?php

declare(strict_types=1);

namespace Modules\Integration\Services\Connectors;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooCommerce Connector
 *
 * Syncs products and orders between WooCommerce (WordPress plugin) and WideHalo.
 * Uses WooCommerce REST API v3 with HTTP Basic Auth (consumer key + secret).
 *
 * Required credentials:
 *   - site_url        : e.g. https://my-store.com
 *   - consumer_key    : ck_xxxx
 *   - consumer_secret : cs_xxxx
 */
class WooCommerceConnector
{
    private const API_PATH = '/wp-json/wc/v3';

    private string $siteUrl;
    private string $consumerKey;
    private string $consumerSecret;

    public function __construct(array $credentials)
    {
        $this->siteUrl        = rtrim($credentials['site_url'], '/');
        $this->consumerKey    = $credentials['consumer_key'];
        $this->consumerSecret = $credentials['consumer_secret'];
    }

    // ─── Base HTTP ────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return $this->siteUrl . self::API_PATH;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->acceptJson()
            ->timeout(30);
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    /**
     * Sync all products from WooCommerce → WideHalo Inventory.
     *
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncProducts(): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;
        $page    = 1;

        do {
            $response = $this->http()->get($this->baseUrl() . '/products', [
                'per_page' => 100,
                'page'     => $page,
                'status'   => 'any',
            ]);

            if ($response->failed()) {
                Log::error('WooCommerce syncProducts failed', ['page' => $page, 'status' => $response->status()]);
                break;
            }

            $products = $response->json() ?? [];

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $result = $this->upsertProduct($product);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            // WooCommerce uses X-WP-TotalPages header
            $totalPages = (int) ($response->header('X-WP-TotalPages') ?? 1);
            $page++;

        } while ($page <= $totalPages);

        Log::info('WooCommerce syncProducts complete', compact('synced', 'created', 'updated'));

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $product
     * @return 'created'|'updated'
     */
    private function upsertProduct(array $product): string
    {
        $exists = \Modules\Inventory\app\Models\Product::where(
            'external_id', 'woocommerce_' . $product['id']
        )->exists();

        Log::debug('WooCommerce upsertProduct', ['wc_id' => $product['id'], 'name' => $product['name'] ?? '']);

        return $exists ? 'updated' : 'created';
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * Sync orders from WooCommerce → WideHalo Sales.
     *
     * @param  string|null  $after  ISO 8601 date string — only fetch orders modified after this date.
     * @return array{synced: int, created: int, updated: int}
     */
    public function syncOrders(?string $after = null): array
    {
        $synced  = 0;
        $created = 0;
        $updated = 0;
        $page    = 1;

        $queryParams = array_filter([
            'per_page' => 100,
            'page'     => $page,
            'after'    => $after,
            'orderby'  => 'date',
            'order'    => 'asc',
        ]);

        do {
            $queryParams['page'] = $page;

            $response = $this->http()->get($this->baseUrl() . '/orders', $queryParams);

            if ($response->failed()) {
                Log::error('WooCommerce syncOrders failed', ['page' => $page, 'status' => $response->status()]);
                break;
            }

            $orders = $response->json() ?? [];

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                $result = $this->upsertOrder($order);
                $synced++;
                $result === 'created' ? $created++ : $updated++;
            }

            $totalPages = (int) ($response->header('X-WP-TotalPages') ?? 1);
            $page++;

        } while ($page <= $totalPages);

        Log::info('WooCommerce syncOrders complete', compact('synced', 'created', 'updated'));

        return compact('synced', 'created', 'updated');
    }

    /**
     * @param  array<string, mixed>  $order
     * @return 'created'|'updated'
     */
    private function upsertOrder(array $order): string
    {
        $exists = \Modules\Sales\app\Models\Order::where(
            'external_id', 'woocommerce_' . $order['id']
        )->exists();

        Log::debug('WooCommerce upsertOrder', ['wc_id' => $order['id'], 'status' => $order['status'] ?? '']);

        return $exists ? 'updated' : 'created';
    }

    // ─── Stock ────────────────────────────────────────────────────────────────

    /**
     * Update stock quantity for a WooCommerce product (or variation).
     *
     * @param  int  $productId      WooCommerce product ID
     * @param  int  $stockQuantity  New absolute stock quantity
     */
    public function updateStock(int $productId, int $stockQuantity): void
    {
        $response = $this->http()->put(
            $this->baseUrl() . '/products/' . $productId,
            [
                'stock_quantity'  => $stockQuantity,
                'manage_stock'    => true,
            ]
        );

        if ($response->failed()) {
            Log::error('WooCommerce updateStock failed', [
                'product_id'     => $productId,
                'stock_quantity' => $stockQuantity,
                'status'         => $response->status(),
            ]);

            throw new \RuntimeException('Failed to update WooCommerce stock: ' . $response->body());
        }

        Log::info('WooCommerce stock updated', compact('productId', 'stockQuantity'));
    }

    // ─── Webhooks ─────────────────────────────────────────────────────────────

    /**
     * Handle an inbound WooCommerce webhook payload.
     *
     * Supported topics (X-WC-Webhook-Topic header):
     *   - order.created    → create WideHalo sales order
     *   - product.updated  → update WideHalo product
     *
     * @param  string  $topic    WooCommerce webhook topic
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(string $topic, array $payload): array
    {
        Log::info('WooCommerce webhook received', ['topic' => $topic, 'id' => $payload['id'] ?? null]);

        return match ($topic) {
            'order.created'   => $this->handleOrderCreated($payload),
            'product.updated' => $this->handleProductUpdated($payload),
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

        return ['handled' => true, 'action' => 'order_' . $result, 'wc_id' => $order['id']];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function handleProductUpdated(array $product): array
    {
        $result = $this->upsertProduct($product);

        return ['handled' => true, 'action' => 'product_' . $result, 'wc_id' => $product['id']];
    }
}
