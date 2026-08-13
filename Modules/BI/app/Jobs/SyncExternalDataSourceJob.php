<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Models\ExternalDataSource;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * SyncExternalDataSourceJob
 *
 * Pulls data from external sources like Google Analytics, Shopify, Salesforce, etc.
 * Implements exponential backoff for rate-limited APIs.
 *
 * @property int data_source_id The ID of the external data source
 * @property bool incremental Whether to perform incremental sync
 * @property string job_id Unique identifier for tracking progress
 */
class SyncExternalDataSourceJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly int $data_source_id,
        private readonly bool $incremental = true
    ) {
        $this->jobId = uniqid('sync_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting external data source sync', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'incremental' => $this->incremental,
                'timestamp' => now()->toIso8601String(),
            ]);

            $dataSource = BiDataSource::findOrFail($this->data_source_id);

            // Authenticate with external API
            $authenticated = $this->authenticate($dataSource);

            if (!$authenticated) {
                Log::error('Failed to authenticate with data source', [
                    'job_id' => $this->jobId,
                    'source_id' => $this->data_source_id,
                    'type' => $dataSource->type,
                ]);

                return;
            }

            // Fetch data from external source
            $rawData = $this->fetchExternalData($dataSource);

            if ($rawData === null) {
                Log::warning('No data fetched from external source', [
                    'job_id' => $this->jobId,
                    'source_id' => $this->data_source_id,
                ]);

                return;
            }

            // Transform and validate data
            TransformAndValidateDataJob::dispatch(
                $this->data_source_id,
                $rawData
            );

            // Update data source status
            $dataSource->update([
                'status' => 'active',
                'last_tested_at' => now(),
            ]);

            Log::info('External data source sync completed', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'type' => $dataSource->type,
                'records_fetched' => count($rawData ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('External data source sync failed', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $source = ExternalDataSource::findOrFail($this->data_source_id);
        return $source->company_id;
    }

    /**
     * Authenticate with external data source
     */
    private function authenticate(BiDataSource $dataSource): bool
    {
        try {
            $config = $dataSource->connection_config;

            return match ($dataSource->type) {
                'google_analytics' => $this->authenticateGoogleAnalytics($config),
                'shopify' => $this->authenticateShopify($config),
                'salesforce' => $this->authenticateSalesforce($config),
                'stripe' => $this->authenticateStripe($config),
                'hubspot' => $this->authenticateHubSpot($config),
                default => true,
            };
        } catch (\Throwable $e) {
            Log::error('Authentication failed', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate with Google Analytics API
     *
     * @param array<string, mixed> $config
     */
    private function authenticateGoogleAnalytics(array $config): bool
    {
        // In real implementation, use Google OAuth2
        $accessToken = $config['access_token'] ?? null;

        if (!$accessToken) {
            return false;
        }

        // Test token validity
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get('https://www.googleapis.com/analytics/v3/management/accounts');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Google Analytics authentication test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate with Shopify API
     *
     * @param array<string, mixed> $config
     */
    private function authenticateShopify(array $config): bool
    {
        $store = $config['store'] ?? null;
        $accessToken = $config['access_token'] ?? null;

        if (!$store || !$accessToken) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->get("https://{$store}/admin/api/2024-01/shop.json");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Shopify authentication test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate with Salesforce API
     *
     * @param array<string, mixed> $config
     */
    private function authenticateSalesforce(array $config): bool
    {
        $clientId = $config['client_id'] ?? null;
        $clientSecret = $config['client_secret'] ?? null;

        if (!$clientId || !$clientSecret) {
            return false;
        }

        try {
            // OAuth2 flow
            $response = Http::asForm()->post('https://login.salesforce.com/services/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Salesforce authentication test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate with Stripe API
     *
     * @param array<string, mixed> $config
     */
    private function authenticateStripe(array $config): bool
    {
        $apiKey = $config['api_key'] ?? null;

        if (!$apiKey) {
            return false;
        }

        try {
            // Test API key
            $response = Http::withBasicAuth($apiKey, '')
                ->get('https://api.stripe.com/v1/charges?limit=1');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('Stripe authentication test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Authenticate with HubSpot API
     *
     * @param array<string, mixed> $config
     */
    private function authenticateHubSpot(array $config): bool
    {
        $apiKey = $config['api_key'] ?? null;

        if (!$apiKey) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->get('https://api.hubapi.com/crm/v3/objects/contacts?limit=1');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('HubSpot authentication test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Fetch data from external source with exponential backoff
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchExternalData(BiDataSource $dataSource): ?array
    {
        $config = $dataSource->connection_config;
        $maxRetries = 3;
        $backoff = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $data = match ($dataSource->type) {
                    'google_analytics' => $this->fetchGoogleAnalytics($config),
                    'shopify' => $this->fetchShopify($config),
                    'salesforce' => $this->fetchSalesforce($config),
                    'stripe' => $this->fetchStripe($config),
                    'hubspot' => $this->fetchHubSpot($config),
                    default => null,
                };

                if ($data !== null) {
                    Log::debug('Data fetched successfully', [
                        'job_id' => $this->jobId,
                        'source_type' => $dataSource->type,
                        'record_count' => count($data),
                        'attempt' => $attempt,
                    ]);

                    return $data;
                }
            } catch (\Throwable $e) {
                Log::warning('Data fetch attempt failed', [
                    'job_id' => $this->jobId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                // Exponential backoff
                if ($attempt < $maxRetries) {
                    sleep($backoff);
                    $backoff *= 2;
                }
            }
        }

        return null;
    }

    /**
     * Fetch data from Google Analytics
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchGoogleAnalytics(array $config): ?array
    {
        // Simulate Google Analytics API call
        return [
            ['date' => now()->toDateString(), 'users' => rand(100, 500), 'sessions' => rand(150, 800), 'pageviews' => rand(200, 1000)],
            ['date' => now()->subDay()->toDateString(), 'users' => rand(100, 500), 'sessions' => rand(150, 800), 'pageviews' => rand(200, 1000)],
        ];
    }

    /**
     * Fetch data from Shopify
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchShopify(array $config): ?array
    {
        // Simulate Shopify API call
        return [
            ['id' => 1, 'name' => 'Product A', 'price' => 99.99, 'inventory' => 50],
            ['id' => 2, 'name' => 'Product B', 'price' => 149.99, 'inventory' => 25],
        ];
    }

    /**
     * Fetch data from Salesforce
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchSalesforce(array $config): ?array
    {
        // Simulate Salesforce API call
        return [
            ['id' => 'sf_001', 'name' => 'Account 1', 'annual_revenue' => 1000000, 'industry' => 'Technology'],
            ['id' => 'sf_002', 'name' => 'Account 2', 'annual_revenue' => 500000, 'industry' => 'Finance'],
        ];
    }

    /**
     * Fetch data from Stripe
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchStripe(array $config): ?array
    {
        // Simulate Stripe API call
        return [
            ['id' => 'ch_001', 'amount' => 9999, 'currency' => 'usd', 'status' => 'succeeded'],
            ['id' => 'ch_002', 'amount' => 14999, 'currency' => 'usd', 'status' => 'succeeded'],
        ];
    }

    /**
     * Fetch data from HubSpot
     *
     * @param array<string, mixed> $config
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchHubSpot(array $config): ?array
    {
        // Simulate HubSpot API call
        return [
            ['id' => 'hs_001', 'firstname' => 'John', 'lastname' => 'Doe', 'email' => 'john@example.com'],
            ['id' => 'hs_002', 'firstname' => 'Jane', 'lastname' => 'Smith', 'email' => 'jane@example.com'],
        ];
    }
}
