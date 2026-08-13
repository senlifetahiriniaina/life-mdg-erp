<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

use Illuminate\Support\Facades\Http;

/**
 * REST API external data source connector.
 *
 * Supports bearer token, Basic Auth, or no auth.
 */
class RestApiConnector implements DataSourceConnectorInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function connect(): void
    {
        // Stateless — nothing to persist
    }

    public function testConnection(): array
    {
        $start    = microtime(true);
        $baseUrl  = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            return ['success' => false, 'latency_ms' => 0, 'message' => 'base_url is required.'];
        }

        try {
            $response = $this->buildRequest()->get($baseUrl);
            $latency  = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'    => $response->successful(),
                'latency_ms' => $latency,
                'message'    => $response->successful() ? 'Connection successful.' : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }

    public function fetchData(string $query = ''): array
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $url     = $query !== '' ? $baseUrl . '/' . ltrim($query, '/') : $baseUrl;

        $start    = microtime(true);
        $response = $this->buildRequest()->get($url);
        $response->throw();

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        /** @var array<string, mixed>|list<array<string, mixed>> $body */
        $body = $response->json();

        // Normalise to rows/columns
        $rows    = is_array($body) && array_is_list($body) ? $body : [$body];
        $columns = $rows !== [] && is_array($rows[0]) ? array_keys($rows[0]) : [];

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'duration_ms' => $durationMs,
        ];
    }

    public function getSchema(): array
    {
        // Fetch root endpoint and return top-level keys as pseudo-schema
        $data   = $this->fetchData('');
        $sample = $data['rows'][0] ?? [];

        return [
            [
                'table'   => 'root',
                'columns' => array_map(
                    fn ($key) => ['name' => $key, 'type' => gettype($sample[$key] ?? null), 'nullable' => true],
                    array_keys($sample)
                ),
            ],
        ];
    }

    private function buildRequest(): \Illuminate\Http\Client\PendingRequest
    {
        /** @var array<string, string> $headers */
        $headers  = (array) ($this->config['headers'] ?? []);
        $authType = (string) ($this->config['auth_type'] ?? 'none');
        $request  = Http::withHeaders($headers)->timeout(10);

        return match ($authType) {
            'bearer' => $request->withToken((string) ($this->config['token'] ?? '')),
            'basic'  => $request->withBasicAuth(
                (string) ($this->config['username'] ?? ''),
                (string) ($this->config['password'] ?? '')
            ),
            default  => $request,
        };
    }
}
