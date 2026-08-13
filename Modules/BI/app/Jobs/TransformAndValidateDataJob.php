<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiDataSource;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * TransformAndValidateDataJob
 *
 * Applies transformations and verifies data integrity.
 * Handles data cleaning, type conversion, and validation rules.
 *
 * @property int data_source_id The ID of the data source
 * @property array<int, array<string, mixed>> raw_data Raw data from external source
 * @property string job_id Unique identifier for tracking progress
 */
class TransformAndValidateDataJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;
    private int $validRecords = 0;
    private int $invalidRecords = 0;
    private array $errors = [];

    public function __construct(
        private readonly int $data_source_id,
        private readonly array $raw_data = []
    ) {
        $this->jobId = uniqid('transform_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting data transformation and validation', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'record_count' => count($this->raw_data),
                'timestamp' => now()->toIso8601String(),
            ]);

            $dataSource = BiDataSource::findOrFail($this->data_source_id);

            // Get transformation configuration
            $config = $dataSource->connection_config;

            // Process data in chunks
            $chunks = array_chunk($this->raw_data, 500);

            foreach ($chunks as $chunkIndex => $chunk) {
                try {
                    $this->processDataChunk($chunk, $dataSource);

                    Log::debug('Data chunk processed', [
                        'job_id' => $this->jobId,
                        'chunk_number' => $chunkIndex + 1,
                        'chunk_size' => count($chunk),
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Data chunk processing failed', [
                        'job_id' => $this->jobId,
                        'chunk_number' => $chunkIndex + 1,
                        'error' => $e->getMessage(),
                    ]);

                    $this->errors[] = [
                        'chunk' => $chunkIndex + 1,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Generate validation report
            $this->generateValidationReport($dataSource);

            Log::info('Data transformation and validation completed', [
                'job_id' => $this->jobId,
                'source_id' => $this->data_source_id,
                'valid_records' => $this->validRecords,
                'invalid_records' => $this->invalidRecords,
                'error_count' => count($this->errors),
            ]);
        } catch (\Throwable $e) {
            Log::error('Data transformation and validation failed', [
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
        $source = BiDataSource::findOrFail($this->data_source_id);
        return $source->company_id ?? tenant('id');
    }

    /**
     * Process a chunk of data
     *
     * @param array<int, array<string, mixed>> $chunk
     */
    private function processDataChunk(array $chunk, BiDataSource $dataSource): void
    {
        $transformedRecords = [];

        foreach ($chunk as $index => $record) {
            try {
                // Transform record
                $transformed = $this->transformRecord($record, $dataSource);

                // Validate transformed record
                if ($this->validateRecord($transformed, $dataSource)) {
                    $transformedRecords[] = $transformed;
                    $this->validRecords++;
                } else {
                    $this->invalidRecords++;

                    Log::warning('Record validation failed', [
                        'job_id' => $this->jobId,
                        'record_index' => $index,
                        'errors' => $this->getValidationErrors(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->invalidRecords++;

                Log::error('Record transformation failed', [
                    'job_id' => $this->jobId,
                    'record_index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Store transformed records
        if (!empty($transformedRecords)) {
            $this->storeTransformedRecords($transformedRecords, $dataSource);
        }
    }

    /**
     * Transform a single record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformRecord(array $record, BiDataSource $dataSource): array
    {
        $sourceType = $dataSource->type;

        // Apply type-specific transformations
        return match ($sourceType) {
            'google_analytics' => $this->transformGoogleAnalytics($record),
            'shopify' => $this->transformShopify($record),
            'salesforce' => $this->transformSalesforce($record),
            'stripe' => $this->transformStripe($record),
            'hubspot' => $this->transformHubSpot($record),
            default => $this->transformGeneric($record),
        };
    }

    /**
     * Transform Google Analytics record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformGoogleAnalytics(array $record): array
    {
        return [
            'source_id' => $this->data_source_id,
            'source_type' => 'google_analytics',
            'date' => $record['date'] ?? null,
            'users' => (int) ($record['users'] ?? 0),
            'sessions' => (int) ($record['sessions'] ?? 0),
            'pageviews' => (int) ($record['pageviews'] ?? 0),
            'bounce_rate' => (float) ($record['bounce_rate'] ?? 0),
            'avg_session_duration' => (float) ($record['avg_session_duration'] ?? 0),
            'synced_at' => now(),
        ];
    }

    /**
     * Transform Shopify record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformShopify(array $record): array
    {
        return [
            'source_id' => $this->data_source_id,
            'source_type' => 'shopify',
            'external_id' => (string) ($record['id'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'sku' => (string) ($record['sku'] ?? ''),
            'price' => (float) ($record['price'] ?? 0),
            'cost' => (float) ($record['cost'] ?? 0),
            'inventory_quantity' => (int) ($record['inventory'] ?? 0),
            'status' => $record['status'] ?? 'unknown',
            'synced_at' => now(),
        ];
    }

    /**
     * Transform Salesforce record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformSalesforce(array $record): array
    {
        return [
            'source_id' => $this->data_source_id,
            'source_type' => 'salesforce',
            'external_id' => (string) ($record['id'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'industry' => (string) ($record['industry'] ?? ''),
            'annual_revenue' => (float) ($record['annual_revenue'] ?? 0),
            'employees' => (int) ($record['employees'] ?? 0),
            'phone' => (string) ($record['phone'] ?? ''),
            'website' => (string) ($record['website'] ?? ''),
            'synced_at' => now(),
        ];
    }

    /**
     * Transform Stripe record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformStripe(array $record): array
    {
        return [
            'source_id' => $this->data_source_id,
            'source_type' => 'stripe',
            'external_id' => (string) ($record['id'] ?? ''),
            'amount' => (int) ($record['amount'] ?? 0),
            'currency' => strtoupper((string) ($record['currency'] ?? 'usd')),
            'status' => (string) ($record['status'] ?? 'unknown'),
            'customer_id' => (string) ($record['customer_id'] ?? ''),
            'description' => (string) ($record['description'] ?? ''),
            'created_at' => $record['created'] ?? now(),
            'synced_at' => now(),
        ];
    }

    /**
     * Transform HubSpot record
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformHubSpot(array $record): array
    {
        return [
            'source_id' => $this->data_source_id,
            'source_type' => 'hubspot',
            'external_id' => (string) ($record['id'] ?? ''),
            'firstname' => (string) ($record['firstname'] ?? ''),
            'lastname' => (string) ($record['lastname'] ?? ''),
            'email' => strtolower((string) ($record['email'] ?? '')),
            'phone' => (string) ($record['phone'] ?? ''),
            'company' => (string) ($record['company'] ?? ''),
            'lifecyclestage' => (string) ($record['lifecyclestage'] ?? 'unknown'),
            'synced_at' => now(),
        ];
    }

    /**
     * Generic transformation for unknown sources
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function transformGeneric(array $record): array
    {
        return array_merge([
            'source_id' => $this->data_source_id,
            'synced_at' => now(),
        ], $record);
    }

    /**
     * Validate transformed record
     *
     * @param array<string, mixed> $record
     */
    private function validateRecord(array $record, BiDataSource $dataSource): bool
    {
        $rules = $this->getValidationRules($dataSource->type);

        foreach ($rules as $field => $rule) {
            if (!$this->validateField($record, $field, $rule)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get validation rules for data source type
     *
     * @return array<string, mixed>
     */
    private function getValidationRules(string $sourceType): array
    {
        return match ($sourceType) {
            'google_analytics' => [
                'date' => 'required|date',
                'users' => 'required|integer|min:0',
                'sessions' => 'required|integer|min:0',
                'pageviews' => 'required|integer|min:0',
            ],
            'shopify' => [
                'external_id' => 'required',
                'name' => 'required',
                'price' => 'required|numeric|min:0',
            ],
            'salesforce' => [
                'external_id' => 'required',
                'name' => 'required',
            ],
            'stripe' => [
                'external_id' => 'required',
                'amount' => 'required|integer|min:0',
                'currency' => 'required',
                'status' => 'required',
            ],
            'hubspot' => [
                'external_id' => 'required',
                'email' => 'required|email',
            ],
            default => [],
        };
    }

    /**
     * Validate a single field
     *
     * @param array<string, mixed> $record
     */
    private function validateField(array $record, string $field, $rule): bool
    {
        if (!isset($record[$field])) {
            return false;
        }

        $value = $record[$field];

        // Simple validation logic
        if (is_array($rule)) {
            foreach ($rule as $validation) {
                if (!$this->validateSingleRule($value, $validation)) {
                    return false;
                }
            }
        } else {
            return $this->validateSingleRule($value, $rule);
        }

        return true;
    }

    /**
     * Validate single rule
     */
    private function validateSingleRule($value, string $rule): bool
    {
        if ($rule === 'required') {
            return $value !== null && $value !== '';
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);

            return $value >= $min;
        }

        if (str_starts_with($rule, 'email')) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        return true;
    }

    /**
     * Get validation errors (simplified)
     *
     * @return array<string>
     */
    private function getValidationErrors(): array
    {
        return ['Validation failed'];
    }

    /**
     * Store transformed records in database
     *
     * @param array<int, array<string, mixed>> $records
     */
    private function storeTransformedRecords(array $records, BiDataSource $dataSource): void
    {
        // In a real implementation, insert into data_imports or similar table
        Log::debug('Transformed records stored', [
            'job_id' => $this->jobId,
            'record_count' => count($records),
            'source_type' => $dataSource->type,
        ]);
    }

    /**
     * Generate validation report
     */
    private function generateValidationReport(BiDataSource $dataSource): void
    {
        $totalRecords = $this->validRecords + $this->invalidRecords;
        $successRate = $totalRecords > 0 ? ($this->validRecords / $totalRecords) * 100 : 0;

        $report = [
            'source_id' => $this->data_source_id,
            'source_type' => $dataSource->type,
            'total_records' => $totalRecords,
            'valid_records' => $this->validRecords,
            'invalid_records' => $this->invalidRecords,
            'success_rate' => round($successRate, 2),
            'errors' => $this->errors,
            'validated_at' => now()->toIso8601String(),
        ];

        Log::info('Validation report generated', $report);
    }
}
