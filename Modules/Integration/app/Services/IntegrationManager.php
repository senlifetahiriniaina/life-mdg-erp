<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Integration\Models\Integration;
use Modules\Integration\Models\IntegrationSyncLog;

/**
 * IntegrationManager
 *
 * Central service for managing all external integrations across tenants.
 * Provides a unified API to list, connect, disconnect, test, and sync integrations.
 */
class IntegrationManager
{
    /**
     * Registry of all available integrations with metadata.
     */
    private const REGISTRY = [
        // ── Africa First: Mobile Money ────────────────────────────────────────
        'orange-money' => [
            'name'        => 'Orange Money',
            'category'    => 'payment_africa',
            'description' => 'Paiement mobile Orange Money (SN, CI, CM, GN, BF, ML, NE)',
            'icon'        => 'orange-money',
            'connector'   => \Modules\Integration\Services\Connectors\OrangeMoneyConnector::class,
            'countries'   => ['SN', 'CI', 'CM', 'GN', 'BF', 'ML', 'NE'],
            'credentials' => ['api_key', 'merchant_id', 'secret_key', 'environment'],
        ],
        'wave' => [
            'name'        => 'Wave',
            'category'    => 'payment_africa',
            'description' => 'Paiement mobile Wave (SN, CI)',
            'icon'        => 'wave',
            'connector'   => \Modules\Integration\Services\Connectors\WaveConnector::class,
            'countries'   => ['SN', 'CI'],
            'credentials' => ['api_key', 'secret_key', 'webhook_secret'],
        ],
        'mtn-momo' => [
            'name'        => 'MTN MoMo',
            'category'    => 'payment_africa',
            'description' => 'Mobile Money MTN (GH, NG, CM, CI, UG, RW, ZM)',
            'icon'        => 'mtn-momo',
            'connector'   => \Modules\Integration\Services\Connectors\MtnMomoConnector::class,
            'countries'   => ['GH', 'NG', 'CM', 'CI', 'UG', 'RW', 'ZM'],
            'credentials' => ['subscription_key', 'api_user', 'api_key', 'environment'],
        ],
        'mpesa' => [
            'name'        => 'M-Pesa',
            'category'    => 'payment_africa',
            'description' => 'Safaricom M-Pesa (KE, TZ)',
            'icon'        => 'mpesa',
            'connector'   => \Modules\Integration\Services\Connectors\MPesaConnector::class,
            'countries'   => ['KE', 'TZ'],
            'credentials' => ['consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'environment'],
        ],
        // ── E-commerce ────────────────────────────────────────────────────────
        'shopify' => [
            'name'        => 'Shopify',
            'category'    => 'ecommerce',
            'description' => 'Sync produits, commandes et clients Shopify',
            'icon'        => 'shopify',
            'countries'   => [],
            'connector'   => \Modules\Integration\Services\Connectors\ShopifyConnector::class,
            'credentials' => ['shop_domain', 'api_key', 'api_secret', 'access_token'],
        ],
        'woocommerce' => [
            'name'        => 'WooCommerce',
            'category'    => 'ecommerce',
            'description' => 'Sync produits, commandes et clients WooCommerce',
            'icon'        => 'woocommerce',
            'countries'   => [],
            'connector'   => \Modules\Integration\Services\Connectors\WooCommerceConnector::class,
            'credentials' => ['site_url', 'consumer_key', 'consumer_secret'],
        ],
        'jumia' => [
            'name'        => 'Jumia',
            'category'    => 'ecommerce',
            'description' => 'Marketplace Jumia Afrique (NG, GH, CI, SN, CM, TN, MA, EG, KE)',
            'icon'        => 'jumia',
            'connector'   => \Modules\Integration\Services\Connectors\JumiaConnector::class,
            'countries'   => ['NG', 'GH', 'CI', 'SN', 'CM', 'TN', 'MA', 'EG', 'KE'],
            'credentials' => ['api_key', 'seller_id', 'environment'],
        ],
        // ── Business Tools ────────────────────────────────────────────────────
        'google-workspace' => [
            'name'        => 'Google Workspace',
            'category'    => 'business',
            'description' => 'Calendrier, contacts et Gmail',
            'icon'        => 'google-workspace',
            'countries'   => [],
            'connector'   => \Modules\Integration\Services\Connectors\GoogleWorkspaceConnector::class,
            'credentials' => ['client_id', 'client_secret', 'redirect_uri'],
        ],
        'zapier' => [
            'name'        => 'Zapier',
            'category'    => 'automation',
            'description' => 'Automatisations via Zapier webhooks',
            'icon'        => 'zapier',
            'countries'   => [],
            'connector'   => \Modules\Integration\Services\Connectors\ZapierConnector::class,
            'credentials' => ['webhook_secret'],
        ],
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns all available integrations with their current status for this tenant.
     *
     * @return array<string, mixed>
     */
    public function getAvailable(int $tenantId): array
    {
        $connected = Integration::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('integration_key');

        $result = [];
        foreach (self::REGISTRY as $key => $meta) {
            $integration = $connected->get($key);
            $result[] = array_merge($meta, [
                'key'            => $key,
                'status'         => $integration ? $integration->status : 'disconnected',
                'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
                'sync_count'     => $integration?->sync_count ?? 0,
                'error_count'    => $integration?->error_count ?? 0,
                'integration_id' => $integration?->id,
            ]);
        }

        return $result;
    }

    /**
     * Connects/authenticates a new integration for the tenant.
     */
    public function connect(int $tenantId, string $integrationKey, array $credentials): Integration
    {
        $this->assertKeyExists($integrationKey);

        $meta = self::REGISTRY[$integrationKey];

        /** @var Integration $integration */
        $integration = Integration::updateOrCreate(
            ['tenant_id' => $tenantId, 'integration_key' => $integrationKey],
            [
                'name'        => $meta['name'],
                'status'      => 'connected',
                'credentials' => $credentials,   // encrypted via model cast
                'settings'    => [],
                'error_count' => 0,
            ]
        );

        Log::info("Integration connected: {$integrationKey} for tenant {$tenantId}");

        return $integration;
    }

    /**
     * Disconnects an integration.
     */
    public function disconnect(int $tenantId, string $integrationKey): void
    {
        Integration::where('tenant_id', $tenantId)
            ->where('integration_key', $integrationKey)
            ->update(['status' => 'disconnected', 'credentials' => null]);

        Log::info("Integration disconnected: {$integrationKey} for tenant {$tenantId}");
    }

    /**
     * Tests the connection to an external API (ping).
     */
    public function testConnection(Integration $integration): bool
    {
        $connector = $this->resolveConnector($integration);

        try {
            $result = $connector->ping();
            $integration->update(['status' => $result ? 'connected' : 'error']);
            return $result;
        } catch (\Throwable $e) {
            $integration->increment('error_count');
            $integration->update(['status' => 'error']);
            Log::error("Integration ping failed [{$integration->integration_key}]: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Syncs data for an integration.
     *
     * @param  string $direction  'in' | 'out' | 'both'
     * @return array{synced: int, created: int, updated: int, errors: array<string>}
     */
    public function sync(Integration $integration, string $direction = 'both'): array
    {
        $connector = $this->resolveConnector($integration);

        $log = IntegrationSyncLog::create([
            'integration_id' => $integration->id,
            'direction'      => $direction,
            'status'         => 'running',
            'started_at'     => now(),
        ]);

        $result = ['synced' => 0, 'created' => 0, 'updated' => 0, 'errors' => []];

        try {
            if (in_array($direction, ['in', 'both'], true) && method_exists($connector, 'syncIn')) {
                $inResult = $connector->syncIn();
                $result['synced']  += $inResult['synced']  ?? 0;
                $result['created'] += $inResult['created'] ?? 0;
                $result['updated'] += $inResult['updated'] ?? 0;
                $result['errors']   = array_merge($result['errors'], $inResult['errors'] ?? []);
            }

            if (in_array($direction, ['out', 'both'], true) && method_exists($connector, 'syncOut')) {
                $outResult = $connector->syncOut();
                $result['synced']  += $outResult['synced']  ?? 0;
                $result['created'] += $outResult['created'] ?? 0;
                $result['updated'] += $outResult['updated'] ?? 0;
                $result['errors']   = array_merge($result['errors'], $outResult['errors'] ?? []);
            }

            $log->update([
                'status'         => 'success',
                'records_synced' => $result['synced'],
                'errors'         => $result['errors'],
                'completed_at'   => now(),
            ]);

            $integration->increment('sync_count');
            $integration->update(['last_synced_at' => now()]);

        } catch (\Throwable $e) {
            $result['errors'][] = $e->getMessage();
            $log->update([
                'status'       => 'error',
                'errors'       => $result['errors'],
                'completed_at' => now(),
            ]);
            $integration->increment('error_count');
            $integration->update(['status' => 'error']);

            Log::error("Integration sync failed [{$integration->integration_key}]: {$e->getMessage()}");
        }

        return $result;
    }

    /**
     * Retrieve the connector class instance for a given integration record.
     */
    public function resolveConnector(Integration $integration): object
    {
        $this->assertKeyExists($integration->integration_key);

        $connectorClass = self::REGISTRY[$integration->integration_key]['connector'];
        $credentials    = $integration->credentials ?? [];
        $settings       = $integration->settings   ?? [];

        return new $connectorClass($credentials, $settings);
    }

    /**
     * Returns the metadata registry (for API listing).
     *
     * @return array<string, mixed>
     */
    public static function getRegistry(): array
    {
        return self::REGISTRY;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertKeyExists(string $key): void
    {
        if (!array_key_exists($key, self::REGISTRY)) {
            throw new \InvalidArgumentException("Unknown integration key: {$key}");
        }
    }
}
