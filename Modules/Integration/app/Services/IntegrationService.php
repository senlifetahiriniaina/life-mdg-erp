<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\SyncLog;
use Modules\Integration\Models\WebhookEndpoint;

/**
 * Core service for managing third-party integrations.
 *
 * Responsibilities:
 *   - Create and configure connectors (webhook/OAuth2/API key/basic_auth/custom)
 *   - Activate connectors, moving them to 'active' status
 *   - Dispatch outbound webhooks and record results in sync_logs
 *   - Log sync operations (inbound/outbound)
 *   - Provide per-tenant connector statistics
 */
class IntegrationService
{
    // ---------------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------------

    /**
     * Create a new integration connector.
     *
     * @param  array{
     *     tenant_id: int,
     *     name: string,
     *     provider_type: string,
     *     config?: array<string, mixed>|null,
     *     created_by: int,
     * } $data
     */
    public function createConnector(array $data): IntegrationConnector
    {
        $slug = $this->generateSlug($data['tenant_id'], $data['name']);

        return IntegrationConnector::create([
            'tenant_id'     => $data['tenant_id'],
            'name'          => $data['name'],
            'slug'          => $slug,
            'provider_type' => $data['provider_type'],
            'config'        => $data['config'] ?? null,
            'status'        => 'inactive',
            'created_by'    => $data['created_by'],
        ]);
    }

    /**
     * Activate a connector (transitions status → active).
     */
    public function activateConnector(IntegrationConnector $connector): IntegrationConnector
    {
        $connector->update([
            'status'        => 'active',
            'error_message' => null,
        ]);

        return $connector->fresh();
    }

    /**
     * Dispatch an outbound webhook for the given connector.
     *
     * Iterates over the connector's active webhook endpoints, sends the payload,
     * and records a SyncLog regardless of outcome.
     *
     * @param  array<string, mixed> $payload
     */
    public function dispatchWebhook(IntegrationConnector $connector, array $payload): SyncLog
    {
        $startedAt = now();
        $recordsProcessed = 0;
        $recordsFailed = 0;
        $errorDetails = [];

        $endpoints = $connector->webhookEndpoints()->where('is_active', true)->get();

        // Chantier 32.6 (Layer 14f — performance): previously a plain
        // foreach issuing one blocking HTTP call per endpoint sequentially,
        // inside the request-response cycle of `POST connectors/{id}/
        // dispatch` — a connector with N active endpoints (no upper bound
        // enforced anywhere on addWebhook()) could stack up to
        // N * timeout_seconds (each individually validated up to 300s) of
        // wall-clock time before the caller ever got a response — exactly
        // the "external HTTP calls inside a request-response cycle rather
        // than queued" pattern flagged as a plausible slow-API source.
        // Http::pool() fires every endpoint concurrently instead, bounding
        // the whole dispatch to roughly the single slowest endpoint's
        // timeout rather than their sum, while preserving the exact same
        // synchronous return-with-results contract every existing caller/
        // test (IntegrationTest.php) already depends on.
        $results = $endpoints->isEmpty()
            ? []
            : Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($endpoints, $payload) {
                foreach ($endpoints as $endpoint) {
                    $this->buildPooledRequest($pool, $endpoint, $payload);
                }
            });

        foreach ($endpoints as $endpoint) {
            $result = $this->interpretPooledResponse($results[(string) $endpoint->id] ?? null);

            if ($result['success']) {
                $recordsProcessed++;
            } else {
                $recordsFailed++;
                $errorDetails[] = [
                    'endpoint_id' => $endpoint->id,
                    'url'         => $endpoint->url,
                    'error'       => $result['error'],
                    'status_code' => $result['status_code'] ?? null,
                ];
            }
        }

        $status = match (true) {
            $recordsFailed === 0 && $recordsProcessed > 0 => 'success',
            $recordsProcessed > 0 && $recordsFailed > 0   => 'partial',
            default                                         => 'failed',
        };

        // Update connector's last_sync_at and potential error state
        $connectorUpdate = ['last_sync_at' => now()];
        if ($status === 'failed') {
            $connectorUpdate['status'] = 'error';
            $connectorUpdate['error_message'] = 'All webhook endpoints failed during last dispatch.';
        }
        $connector->update($connectorUpdate);

        return SyncLog::create([
            'connector_id'       => $connector->id,
            'tenant_id'          => $connector->tenant_id,
            'direction'          => 'outbound',
            'status'             => $status,
            'payload_size'       => strlen(json_encode($payload)),
            'records_processed'  => $recordsProcessed,
            'records_failed'     => $recordsFailed,
            'error_details'      => empty($errorDetails) ? null : $errorDetails,
            'started_at'         => $startedAt,
            'completed_at'       => now(),
        ]);
    }

    /**
     * Record a sync log entry manually (e.g. for inbound webhook receipt).
     *
     * @param  array{
     *     direction: string,
     *     status: string,
     *     records_processed?: int,
     *     records_failed?: int,
     *     payload_size?: int|null,
     *     error_details?: array<string, mixed>|null,
     * } $result
     */
    public function logSync(IntegrationConnector $connector, string $direction, array $result): SyncLog
    {
        $log = SyncLog::create([
            'connector_id'      => $connector->id,
            'tenant_id'         => $connector->tenant_id,
            'direction'         => $direction,
            'status'            => $result['status'] ?? 'success',
            'payload_size'      => $result['payload_size'] ?? null,
            'records_processed' => $result['records_processed'] ?? 0,
            'records_failed'    => $result['records_failed'] ?? 0,
            'error_details'     => $result['error_details'] ?? null,
            'started_at'        => $result['started_at'] ?? now(),
            'completed_at'      => $result['completed_at'] ?? now(),
        ]);

        $connector->update(['last_sync_at' => now()]);

        return $log;
    }

    /**
     * Return aggregated statistics for a tenant's connectors.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     inactive: int,
     *     error: int,
     *     by_provider: array<string, int>,
     *     total_syncs: int,
     *     successful_syncs: int,
     *     failed_syncs: int,
     * }
     */
    public function getConnectorStats(int $tenantId): array
    {
        $connectors = IntegrationConnector::forTenant($tenantId)->get();

        $byProvider = $connectors
            ->groupBy('provider_type')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $logQuery = SyncLog::forTenant($tenantId);

        return [
            'total'             => $connectors->count(),
            'active'            => $connectors->where('status', 'active')->count(),
            'inactive'          => $connectors->where('status', 'inactive')->count(),
            'error'             => $connectors->where('status', 'error')->count(),
            'by_provider'       => $byProvider,
            'total_syncs'       => (clone $logQuery)->count(),
            'successful_syncs'  => (clone $logQuery)->where('status', 'success')->count(),
            'failed_syncs'      => (clone $logQuery)->where('status', 'failed')->count(),
        ];
    }

    // ---------------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------------

    /**
     * Register a single webhook request into a concurrent Http::pool(),
     * keyed by the endpoint's own id so dispatchWebhook() can match each
     * result back to its endpoint afterwards. Building the request (timeout,
     * custom headers, HMAC signature) is identical to the old sequential
     * sendToEndpoint() — only the actual send() is now async/pooled.
     */
    private function buildPooledRequest(\Illuminate\Http\Client\Pool $pool, WebhookEndpoint $endpoint, array $payload): void
    {
        $request = $pool->as((string) $endpoint->id)->timeout($endpoint->timeout_seconds);

        if (!empty($endpoint->headers)) {
            $request = $request->withHeaders($endpoint->headers);
        }

        if ($endpoint->secret_key) {
            $signature = hash_hmac('sha256', json_encode($payload), $endpoint->secret_key);
            $request = $request->withHeaders(['X-WideHalo-Signature' => "sha256={$signature}"]);
        }

        $request->send($endpoint->method, $endpoint->url, ['json' => $payload]);
    }

    /**
     * Interpret one Http::pool() result slot — a real Response on success
     * or on any non-2xx status, or a Throwable (e.g. ConnectionException,
     * per Illuminate\Http\Client\PendingRequest::pool()'s own documented
     * return type) when the request never completed at all — into the same
     * {success, status_code, error} shape sendToEndpoint() used to return
     * directly, so dispatchWebhook()'s aggregation logic is unchanged.
     *
     * @return array{success: bool, status_code: int|null, error: string|null}
     */
    private function interpretPooledResponse(mixed $result): array
    {
        if ($result instanceof \Throwable) {
            Log::warning('Integration: webhook dispatch failed', ['error' => $result->getMessage()]);

            return ['success' => false, 'status_code' => null, 'error' => $result->getMessage()];
        }

        if (! $result instanceof \Illuminate\Http\Client\Response) {
            // No slot at all for this endpoint (shouldn't happen — every
            // endpoint gets a pool key — but fail closed rather than throw
            // an undefined-index-shaped error if it ever does).
            return ['success' => false, 'status_code' => null, 'error' => 'No response received.'];
        }

        if ($result->successful()) {
            return ['success' => true, 'status_code' => $result->status(), 'error' => null];
        }

        return [
            'success'     => false,
            'status_code' => $result->status(),
            'error'       => "HTTP {$result->status()}: " . $result->body(),
        ];
    }

    /**
     * Generate a unique slug for a connector within a tenant.
     */
    private function generateSlug(int $tenantId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (IntegrationConnector::where('tenant_id', $tenantId)->where('slug', $slug)->withTrashed()->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
