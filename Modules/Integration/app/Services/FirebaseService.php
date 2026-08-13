<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private string $projectId;
    private string $databaseUrl;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->projectId   = config('firebase.project_id', '');
        $this->databaseUrl = config('firebase.database_url', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->projectId);
    }

    /**
     * Send an FCM push notification to a device token.
     */
    public function sendPushNotification(
        string $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->getServerKey(),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'data' => $data,
            ]);

            if ($response->failed()) {
                Log::warning('Firebase::sendPushNotification failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return false;
            }

            $json = $response->json();

            return isset($json['success']) && $json['success'] >= 1;
        } catch (\Throwable $e) {
            Log::error('Firebase::sendPushNotification exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Write (set) data at a Realtime Database path.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    public function set(string $path, array $data): array
    {
        try {
            $url = $this->buildDatabaseUrl($path);
            $response = Http::withHeaders($this->databaseHeaders())
                ->put($url, $data);

            if ($response->failed()) {
                Log::warning('Firebase::set failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['data' => null, 'error' => $response->body()];
            }

            return ['data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Firebase::set exception', ['path' => $path, 'error' => $e->getMessage()]);

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Read data at a Realtime Database path.
     *
     * @return array{data: array<string, mixed>|null, error: string|null}
     */
    public function get(string $path): array
    {
        try {
            $url = $this->buildDatabaseUrl($path);
            $response = Http::withHeaders($this->databaseHeaders())
                ->get($url);

            if ($response->failed()) {
                Log::warning('Firebase::get failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['data' => null, 'error' => $response->body()];
            }

            return ['data' => $response->json(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Firebase::get exception', ['path' => $path, 'error' => $e->getMessage()]);

            return ['data' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Push a new child node at a Realtime Database path.
     * Returns the generated child key on success, or empty string on failure.
     *
     * @param  array<string, mixed>  $data
     */
    public function push(string $path, array $data): string
    {
        try {
            $url = $this->buildDatabaseUrl($path);
            $response = Http::withHeaders($this->databaseHeaders())
                ->post($url, $data);

            if ($response->failed()) {
                Log::warning('Firebase::push failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return '';
            }

            $json = $response->json();

            return $json['name'] ?? '';
        } catch (\Throwable $e) {
            Log::error('Firebase::push exception', ['path' => $path, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Delete data at a Realtime Database path.
     */
    public function remove(string $path): bool
    {
        try {
            $url = $this->buildDatabaseUrl($path);
            $response = Http::withHeaders($this->databaseHeaders())
                ->delete($url);

            if ($response->failed()) {
                Log::warning('Firebase::remove failed', [
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Firebase::remove exception', ['path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Upload a file to Firebase Storage via the REST API.
     *
     * @return array{name: string|null, bucket: string|null, error: string|null}
     */
    public function uploadFile(
        string $bucket,
        string $path,
        string $contents,
        string $mimeType = 'application/octet-stream'
    ): array {
        try {
            $encodedPath = rawurlencode($path);
            $url = "https://storage.googleapis.com/upload/storage/v1/b/{$bucket}/o?uploadType=media&name={$encodedPath}";

            $response = Http::withHeaders(array_merge($this->databaseHeaders(), [
                'Content-Type' => $mimeType,
            ]))->withBody($contents, $mimeType)->post($url);

            if ($response->failed()) {
                Log::warning('Firebase::uploadFile failed', [
                    'bucket' => $bucket,
                    'path'   => $path,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return ['name' => null, 'bucket' => null, 'error' => $response->body()];
            }

            $json = $response->json();

            return [
                'name'   => $json['name'] ?? $path,
                'bucket' => $json['bucket'] ?? $bucket,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Firebase::uploadFile exception', ['path' => $path, 'error' => $e->getMessage()]);

            return ['name' => null, 'bucket' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the Firebase Realtime Database REST URL for a given path.
     */
    private function buildDatabaseUrl(string $path): string
    {
        $base = rtrim($this->databaseUrl, '/');
        $path = ltrim($path, '/');

        return "{$base}/{$path}.json";
    }

    /**
     * Return HTTP headers for Firebase REST API calls.
     *
     * @return array<string, string>
     */
    private function databaseHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($this->accessToken !== null) {
            $headers['Authorization'] = "Bearer {$this->accessToken}";
        }

        return $headers;
    }

    /**
     * Get the FCM server key from credentials file or config.
     * Falls back to an empty string when credentials file is absent.
     */
    private function getServerKey(): string
    {
        $credentialsFile = config('firebase.credentials', '');

        if (! empty($credentialsFile) && file_exists($credentialsFile)) {
            $credentials = json_decode(file_get_contents($credentialsFile), true);

            return $credentials['private_key'] ?? '';
        }

        return '';
    }
}
