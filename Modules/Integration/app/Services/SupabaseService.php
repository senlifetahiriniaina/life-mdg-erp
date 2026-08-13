<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseService
{
    private string $url;
    private string $serviceKey;
    private string $anonKey;

    public function __construct()
    {
        $this->url        = config('supabase.url', '');
        $this->serviceKey = config('supabase.service_key', '');
        $this->anonKey    = config('supabase.anon_key', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->url) && ! empty($this->serviceKey);
    }

    /**
     * Query a table via PostgREST.
     *
     * @return array{data: array<int, array<string, mixed>>, error: string|null}
     */
    public function from(string $table): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->get("{$this->url}/rest/v1/{$table}");

            if ($response->failed()) {
                Log::warning('Supabase::from failed', [
                    'table'  => $table,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['data' => [], 'error' => $response->body()];
            }

            return ['data' => $response->json() ?? [], 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Supabase::from exception', ['table' => $table, 'error' => $e->getMessage()]);

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Insert rows into a table.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    public function insert(string $table, array $data): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->post("{$this->url}/rest/v1/{$table}", $data);

            if ($response->failed()) {
                Log::warning('Supabase::insert failed', [
                    'table'  => $table,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['data' => null, 'error' => $response->body()];
            }

            return ['data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Supabase::insert exception', ['table' => $table, 'error' => $e->getMessage()]);

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update rows matching filters.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters  Key-value pairs applied as PostgREST eq filters
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    public function update(string $table, array $data, array $filters): array
    {
        try {
            $query = $this->buildFilterQuery($filters);
            $response = Http::withHeaders($this->headers())
                ->patch("{$this->url}/rest/v1/{$table}?{$query}", $data);

            if ($response->failed()) {
                Log::warning('Supabase::update failed', [
                    'table'  => $table,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['data' => null, 'error' => $response->body()];
            }

            return ['data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Supabase::update exception', ['table' => $table, 'error' => $e->getMessage()]);

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete rows matching filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array{success: bool, error: string|null}
     */
    public function delete(string $table, array $filters): array
    {
        try {
            $query = $this->buildFilterQuery($filters);
            $response = Http::withHeaders($this->headers())
                ->delete("{$this->url}/rest/v1/{$table}?{$query}");

            if ($response->failed()) {
                Log::warning('Supabase::delete failed', [
                    'table'  => $table,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['success' => false, 'error' => $response->body()];
            }

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Supabase::delete exception', ['table' => $table, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Upload a file to Supabase Storage.
     *
     * @return array{path: string|null, url: string|null, error: string|null}
     */
    public function uploadFile(string $bucket, string $path, string $contents): array
    {
        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Content-Type' => 'application/octet-stream',
            ]))->withBody($contents, 'application/octet-stream')
                ->post("{$this->url}/storage/v1/object/{$bucket}/{$path}");

            if ($response->failed()) {
                Log::warning('Supabase::uploadFile failed', [
                    'bucket' => $bucket,
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['path' => null, 'url' => null, 'error' => $response->body()];
            }

            return [
                'path'  => $path,
                'url'   => $this->getPublicUrl($bucket, $path),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Supabase::uploadFile exception', ['path' => $path, 'error' => $e->getMessage()]);

            return ['path' => null, 'url' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the public URL for a stored file.
     */
    public function getPublicUrl(string $bucket, string $path): string
    {
        return "{$this->url}/storage/v1/object/public/{$bucket}/{$path}";
    }

    /**
     * Return Realtime connection config for the client side.
     *
     * @return array{url: string, anon_key: string, channel: string, enabled: bool}
     */
    public function getRealtimeConfig(): array
    {
        return [
            'url'      => $this->url,
            'anon_key' => $this->anonKey,
            'channel'  => config('supabase.realtime.channel', 'widehalo'),
            'enabled'  => (bool) config('supabase.realtime.enabled', false),
        ];
    }

    /**
     * Build the HTTP headers for Supabase REST/Storage requests.
     *
     * @return array<string, string>
     */
    private function headers(bool $useServiceKey = true): array
    {
        $key = $useServiceKey ? $this->serviceKey : $this->anonKey;

        return [
            'Authorization' => "Bearer {$key}",
            'apikey'        => $key,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ];
    }

    /**
     * Build PostgREST query string from filter key-value pairs (equality only).
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildFilterQuery(array $filters): string
    {
        $parts = [];
        foreach ($filters as $column => $value) {
            $parts[] = urlencode($column) . '=eq.' . urlencode((string) $value);
        }

        return implode('&', $parts);
    }
}
