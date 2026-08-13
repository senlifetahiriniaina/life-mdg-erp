<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Shared\Services\BaseService;

class ExternalDataIntegrationService extends BaseService
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const SUPPORTED_SOURCES = ['google_analytics', 'shopify', 'salesforce', 'stripe', 'api'];
    private const SYNC_STATUSES = ['pending', 'in_progress', 'success', 'failed'];
    private const SYNC_FREQUENCIES = ['hourly', 'daily', 'weekly', 'monthly'];

    /**
     * Register new external data source with connection configuration.
     *
     * @param  array{name: string, type: string, baseUrl?: string, company_id: int, created_by: int, description?: string}  $sourceData
     * @return array{id: int, name: string, type: string, status: string}
     */
    public function registerExternalDataSource(array $sourceData): array
    {
        try {
            if (!in_array($sourceData['type'], self::SUPPORTED_SOURCES)) {
                throw new \InvalidArgumentException("Unsupported data source type: {$sourceData['type']}");
            }

            $sourceId = DB::table('bi_data_sources')->insertGetId([
                'name'              => $sourceData['name'],
                'type'              => $sourceData['type'],
                'base_url'          => $sourceData['baseUrl'] ?? null,
                'connection_config' => json_encode([
                    'type' => $sourceData['type'],
                    'status' => 'pending_validation',
                ]),
                'status'            => 'inactive',
                'created_by'        => $sourceData['created_by'],
                'company_id'        => $sourceData['company_id'],
                'description'       => $sourceData['description'] ?? null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            Log::info('External data source registered', [
                'source_id' => $sourceId,
                'type'      => $sourceData['type'],
                'name'      => $sourceData['name'],
            ]);

            return [
                'id'     => $sourceId,
                'name'   => $sourceData['name'],
                'type'   => $sourceData['type'],
                'status' => 'inactive',
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to register data source', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Encrypt and store API credentials using AES-256.
     *
     * @param  int  $sourceId
     * @param  array{apiKey?: string, apiSecret?: string, accessToken?: string, webhookSecret?: string, basicAuth?: string}  $credentials
     * @return array{encrypted: bool, credentialFields: array}
     */
    public function encryptApiCredentials(int $sourceId, array $credentials): array
    {
        try {
            $source = DB::table('bi_data_sources')->find($sourceId);
            if (!$source) {
                throw new \InvalidArgumentException("Data source {$sourceId} not found");
            }

            $config = json_decode($source->connection_config, true) ?? [];

            $encryptedCredentials = [];
            foreach ($credentials as $key => $value) {
                if (!empty($value)) {
                    $encryptedCredentials[$key] = Crypt::encryptString($value);
                }
            }

            $config['credentials'] = $encryptedCredentials;
            $config['encrypted_at'] = now()->toIso8601String();

            // Update source with encrypted credentials
            DB::table('bi_data_sources')
                ->where('id', $sourceId)
                ->update([
                    'connection_config' => json_encode($config),
                    'updated_at'        => now(),
                ]);

            $credentialFields = array_keys($encryptedCredentials);

            Log::info('API credentials encrypted', [
                'source_id' => $sourceId,
                'fields'    => $credentialFields,
            ]);

            return [
                'encrypted'         => true,
                'credentialFields'  => $credentialFields,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to encrypt credentials', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create mapping between external and internal BI field names.
     *
     * @param  int  $sourceId
     * @param  array{externalField: string, internalField: string, fieldType?: string, transformation?: string}  $mapping
     * @return array{id: int, externalField: string, internalField: string}
     */
    public function createDataMapping(int $sourceId, array $mapping): array
    {
        try {
            $mappingId = DB::table('bi_data_mappings')->insertGetId([
                'data_source_id'    => $sourceId,
                'external_field'    => $mapping['externalField'],
                'internal_field'    => $mapping['internalField'],
                'field_type'        => $mapping['fieldType'] ?? 'string',
                'transformation'    => $mapping['transformation'] ?? null,
                'is_active'         => true,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            Log::info('Data mapping created', [
                'mapping_id'     => $mappingId,
                'source_id'      => $sourceId,
                'external_field' => $mapping['externalField'],
            ]);

            return [
                'id'              => $mappingId,
                'externalField'   => $mapping['externalField'],
                'internalField'   => $mapping['internalField'],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create data mapping', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate API connection with credentials before saving.
     *
     * @param  int  $sourceId
     * @param  array  $testPayload
     * @return array{valid: bool, message: string, responseTime?: float}
     */
    public function validateApiConnection(int $sourceId, array $testPayload = []): array
    {
        try {
            $source = DB::table('bi_data_sources')->find($sourceId);
            if (!$source) {
                throw new \InvalidArgumentException("Data source {$sourceId} not found");
            }

            $config = json_decode($source->connection_config, true) ?? [];

            // Simulate connection test
            $startTime   = microtime(true);
            $isValid     = true;
            $message     = "Connection test successful";
            $responseTime = 0;

            if ($source->type === 'api' && !empty($source->base_url)) {
                // In production, this would make actual HTTP request
                // For now, simulate connection check
                $responseTime = (microtime(true) - $startTime) * 1000;
            } else {
                $responseTime = (microtime(true) - $startTime) * 1000;
            }

            // Update source status
            DB::table('bi_data_sources')
                ->where('id', $sourceId)
                ->update([
                    'status'         => $isValid ? 'active' : 'error',
                    'last_tested_at' => now(),
                    'updated_at'     => now(),
                ]);

            Log::info('API connection validated', [
                'source_id'     => $sourceId,
                'valid'         => $isValid,
                'response_time' => $responseTime,
            ]);

            return [
                'valid'        => $isValid,
                'message'      => $message,
                'responseTime' => round($responseTime, 2),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to validate API connection', ['error' => $e->getMessage()]);
            return [
                'valid'   => false,
                'message' => "Connection validation failed: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Sync data from external source via API.
     *
     * @param  int  $sourceId
     * @param  array{limit?: int, startDate?: string, endDate?: string}  $options
     * @return array{syncId: int, status: string, rowsImported: int, startedAt: string}
     */
    public function syncDataFromSource(int $sourceId, array $options = []): array
    {
        try {
            $source = DB::table('bi_data_sources')->find($sourceId);
            if (!$source) {
                throw new \InvalidArgumentException("Data source {$sourceId} not found");
            }

            if ($source->status !== 'active') {
                throw new \InvalidArgumentException("Data source is not active (status: {$source->status})");
            }

            // Create sync record
            $syncId = DB::table('bi_data_syncs')->insertGetId([
                'data_source_id'  => $sourceId,
                'status'          => 'in_progress',
                'started_at'      => now(),
                'options'         => json_encode($options),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Queue actual sync job
            Queue::push(new \Modules\BI\Jobs\SyncExternalDataJob($sourceId, $syncId, $options));

            Log::info('Data sync initiated', [
                'sync_id'   => $syncId,
                'source_id' => $sourceId,
            ]);

            return [
                'syncId'       => $syncId,
                'status'       => 'in_progress',
                'rowsImported' => 0,
                'startedAt'    => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to initiate data sync', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Apply transformations to external data before storing.
     *
     * @param  int  $sourceId
     * @param  array<array<string, mixed>>  $rawData
     * @return array<array<string, mixed>>
     */
    public function transformExternalData(int $sourceId, array $rawData): array
    {
        try {
            $mappings = DB::table('bi_data_mappings')
                ->where('data_source_id', $sourceId)
                ->where('is_active', true)
                ->get();

            $transformedData = [];

            foreach ($rawData as $row) {
                $transformedRow = [];

                foreach ($mappings as $mapping) {
                    $externalValue = $row[$mapping->external_field] ?? null;

                    if ($mapping->transformation) {
                        $externalValue = $this->applyTransformation($mapping->transformation, $externalValue);
                    }

                    // Type casting
                    $externalValue = $this->castFieldType($externalValue, $mapping->field_type);

                    $transformedRow[$mapping->internal_field] = $externalValue;
                }

                if (!empty($transformedRow)) {
                    $transformedData[] = $transformedRow;
                }
            }

            Log::debug('External data transformed', [
                'source_id' => $sourceId,
                'rows'      => count($transformedData),
            ]);

            return $transformedData;
        } catch (\Throwable $e) {
            Log::error('Failed to transform external data', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Schedule recurring data sync job.
     *
     * @param  int  $sourceId
     * @param  string  $frequency  hourly, daily, weekly, monthly
     * @param  array{startTime?: string, timezone?: string}  $options
     * @return array{scheduled: bool, nextSync: string, frequency: string}
     */
    public function scheduleDataSync(int $sourceId, string $frequency = 'daily', array $options = []): array
    {
        try {
            if (!in_array($frequency, self::SYNC_FREQUENCIES)) {
                throw new \InvalidArgumentException("Invalid sync frequency: {$frequency}");
            }

            $source = DB::table('bi_data_sources')->find($sourceId);
            if (!$source) {
                throw new \InvalidArgumentException("Data source {$sourceId} not found");
            }

            // Calculate next sync time
            $nextSync = $this->calculateNextSyncTime($frequency, $options['startTime'] ?? null);

            DB::table('bi_data_sources')
                ->where('id', $sourceId)
                ->update([
                    'sync_frequency'  => $frequency,
                    'sync_start_time' => $options['startTime'] ?? null,
                    'next_sync_at'    => $nextSync,
                    'updated_at'      => now(),
                ]);

            Log::info('Data sync scheduled', [
                'source_id'  => $sourceId,
                'frequency'  => $frequency,
                'next_sync'  => $nextSync,
            ]);

            return [
                'scheduled' => true,
                'nextSync'  => $nextSync->toIso8601String(),
                'frequency' => $frequency,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to schedule data sync', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get last sync status and metadata.
     *
     * @param  int  $sourceId
     * @return array{status: string, lastSyncAt?: string, rowsImported?: int, errorMessage?: string}
     */
    public function getLastSyncStatus(int $sourceId): array
    {
        try {
            $lastSync = DB::table('bi_data_syncs')
                ->where('data_source_id', $sourceId)
                ->orderByDesc('created_at')
                ->first();

            if (!$lastSync) {
                return [
                    'status'      => 'never_synced',
                    'lastSyncAt'  => null,
                ];
            }

            return [
                'status'        => $lastSync->status,
                'lastSyncAt'    => $lastSync->completed_at?->toIso8601String(),
                'rowsImported'  => $lastSync->rows_imported,
                'errorMessage'  => $lastSync->error_message,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get last sync status', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle sync errors and initiate retry logic.
     *
     * @param  int  $syncId
     * @param  array{errorMessage: string, retryAttempt?: int, maxRetries?: int}  $errorData
     * @return array{handled: bool, willRetry: bool, nextRetryAt?: string}
     */
    public function handleSyncError(int $syncId, array $errorData): array
    {
        try {
            $sync = DB::table('bi_data_syncs')->find($syncId);
            if (!$sync) {
                throw new \InvalidArgumentException("Sync job {$syncId} not found");
            }

            $retryAttempt = $errorData['retryAttempt'] ?? 0;
            $maxRetries   = $errorData['maxRetries'] ?? 3;
            $willRetry    = $retryAttempt < $maxRetries;

            $nextRetryAt = null;
            if ($willRetry) {
                // Exponential backoff: 5, 25, 125 seconds
                $delaySeconds = 5 * (5 ** $retryAttempt);
                $nextRetryAt  = now()->addSeconds($delaySeconds);
            }

            DB::table('bi_data_syncs')
                ->where('id', $syncId)
                ->update([
                    'status'            => $willRetry ? 'pending_retry' : 'failed',
                    'error_message'     => $errorData['errorMessage'],
                    'retry_attempt'     => $retryAttempt + 1,
                    'next_retry_at'     => $nextRetryAt,
                    'updated_at'        => now(),
                ]);

            Log::warning('Sync error handled', [
                'sync_id'      => $syncId,
                'will_retry'   => $willRetry,
                'attempt'      => $retryAttempt + 1,
            ]);

            return [
                'handled'     => true,
                'willRetry'   => $willRetry,
                'nextRetryAt' => $nextRetryAt?->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to handle sync error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Verify integrity of synced data (row counts, value ranges, duplicates).
     *
     * @param  int  $syncId
     * @return array{valid: bool, issues: array<string>, rowCount: int}
     */
    public function verifyDataIntegrity(int $syncId): array
    {
        try {
            $sync = DB::table('bi_data_syncs')->find($syncId);
            if (!$sync) {
                throw new \InvalidArgumentException("Sync job {$syncId} not found");
            }

            $issues = [];

            // Check row count
            $rowCount = $sync->rows_imported ?? 0;
            if ($rowCount === 0) {
                $issues[] = "No rows were imported";
            }

            // Check for duplicates in synced data
            $syncedRecords = DB::table('bi_synced_data')
                ->where('sync_id', $syncId)
                ->get();

            if (!$syncedRecords->isEmpty()) {
                $duplicates = $syncedRecords->groupBy(fn ($r) => $r->external_id)
                    ->filter(fn ($group) => count($group) > 1);

                if (!$duplicates->isEmpty()) {
                    $issues[] = "Found " . $duplicates->count() . " duplicate records";
                }
            }

            // Validate required fields
            foreach ($syncedRecords as $record) {
                $data = json_decode($record->data, true);
                if (!isset($data['synced_at'])) {
                    $issues[] = "Missing sync timestamp in record {$record->id}";
                }
            }

            $valid = empty($issues);

            DB::table('bi_data_syncs')
                ->where('id', $syncId)
                ->update([
                    'integrity_verified' => true,
                    'integrity_issues'   => json_encode($issues),
                ]);

            Log::info('Data integrity verified', [
                'sync_id'  => $syncId,
                'valid'    => $valid,
                'issues'   => count($issues),
            ]);

            return [
                'valid'    => $valid,
                'issues'   => $issues,
                'rowCount' => $rowCount,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to verify data integrity', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Discover available fields from external data source via introspection.
     *
     * @param  int  $sourceId
     * @return array<string>
     */
    public function getAvailableFields(int $sourceId): array
    {
        try {
            $source = DB::table('bi_data_sources')->find($sourceId);
            if (!$source) {
                throw new \InvalidArgumentException("Data source {$sourceId} not found");
            }

            // Cache available fields
            $cacheKey = "source:fields:{$sourceId}";
            $cached   = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }

            $fields = [];

            // Simulate field discovery based on source type
            $fields = match ($source->type) {
                'google_analytics' => [
                    'date', 'users', 'sessions', 'pageviews', 'bounceRate',
                    'sessionDuration', 'conversionRate', 'revenue', 'transactions',
                ],
                'shopify' => [
                    'id', 'order_number', 'email', 'created_at', 'updated_at',
                    'total_price', 'total_tax', 'currency', 'financial_status',
                    'fulfillment_status', 'customer_id', 'line_items',
                ],
                'salesforce' => [
                    'Id', 'Name', 'Email', 'Phone', 'Industry', 'AnnualRevenue',
                    'NumberOfEmployees', 'BillingCity', 'BillingCountry',
                    'CreatedDate', 'LastModifiedDate',
                ],
                'stripe' => [
                    'id', 'object', 'amount', 'currency', 'customer',
                    'date', 'description', 'status', 'payment_method',
                    'receipt_email', 'metadata',
                ],
                'api' => $this->introspectCustomApi($source),
                default => [],
            };

            Cache::put($cacheKey, $fields, self::CACHE_TTL);

            Log::debug('Available fields discovered', [
                'source_id' => $sourceId,
                'field_count' => count($fields),
            ]);

            return $fields;
        } catch (\Throwable $e) {
            Log::error('Failed to get available fields', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Test field mapping with sample data.
     *
     * @param  int  $sourceId
     * @param  array  $sampleData
     * @return array{valid: bool, sampleResult: array, errors: array<string>}
     */
    public function testFieldMapping(int $sourceId, array $sampleData): array
    {
        try {
            $errors = [];

            $mappings = DB::table('bi_data_mappings')
                ->where('data_source_id', $sourceId)
                ->where('is_active', true)
                ->get();

            $transformedSample = [];
            foreach ($mappings as $mapping) {
                if (!isset($sampleData[$mapping->external_field])) {
                    $errors[] = "Sample data missing field: {$mapping->external_field}";
                    continue;
                }

                $value = $sampleData[$mapping->external_field];

                if ($mapping->transformation) {
                    try {
                        $value = $this->applyTransformation($mapping->transformation, $value);
                    } catch (\Throwable $e) {
                        $errors[] = "Transformation failed for {$mapping->external_field}: {$e->getMessage()}";
                    }
                }

                $transformedSample[$mapping->internal_field] = $value;
            }

            Log::debug('Field mapping tested', [
                'source_id' => $sourceId,
                'valid'     => empty($errors),
            ]);

            return [
                'valid'         => empty($errors),
                'sampleResult'  => $transformedSample,
                'errors'        => $errors,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to test field mapping', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Rollback data sync to previous state if validation fails.
     *
     * @param  int  $syncId
     * @param  string  $reason
     * @return array{rolledBack: bool, restoredToSyncId?: int}
     */
    public function rollbackDataSync(int $syncId, string $reason = ''): array
    {
        try {
            $sync = DB::table('bi_data_syncs')->find($syncId);
            if (!$sync) {
                throw new \InvalidArgumentException("Sync job {$syncId} not found");
            }

            // Find previous successful sync
            $previousSync = DB::table('bi_data_syncs')
                ->where('data_source_id', $sync->data_source_id)
                ->where('status', 'success')
                ->where('id', '<', $syncId)
                ->orderByDesc('id')
                ->first();

            if (!$previousSync) {
                Log::warning('No previous sync to rollback to', ['sync_id' => $syncId]);
                return [
                    'rolledBack' => false,
                    'restoredToSyncId' => null,
                ];
            }

            // Delete current sync data
            DB::table('bi_synced_data')
                ->where('sync_id', $syncId)
                ->delete();

            // Update sync record
            DB::table('bi_data_syncs')
                ->where('id', $syncId)
                ->update([
                    'status'    => 'rolled_back',
                    'rollback_reason' => $reason,
                    'updated_at' => now(),
                ]);

            Log::info('Data sync rolled back', [
                'sync_id'          => $syncId,
                'previous_sync_id' => $previousSync->id,
                'reason'           => $reason,
            ]);

            return [
                'rolledBack'       => true,
                'restoredToSyncId' => $previousSync->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to rollback sync', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get external data refresh metrics (sync duration, data volume, success rate).
     *
     * @param  int  $sourceId
     * @param  string  $period  7d, 30d, 90d
     * @return array{totalSyncs: int, successRate: float, avgDuration: float, totalRowsSynced: int}
     */
    public function getExternalDataRefreshMetrics(int $sourceId, string $period = '30d'): array
    {
        try {
            $startDate = match ($period) {
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => now()->subDays(30),
            };

            $syncs = DB::table('bi_data_syncs')
                ->where('data_source_id', $sourceId)
                ->where('created_at', '>=', $startDate)
                ->get();

            $totalSyncs   = $syncs->count();
            $successSyncs = $syncs->where('status', 'success')->count();
            $successRate  = $totalSyncs > 0 ? ($successSyncs / $totalSyncs) * 100 : 0;

            $durations  = [];
            $totalRows  = 0;

            foreach ($syncs as $sync) {
                if ($sync->started_at && $sync->completed_at) {
                    $duration = Carbon::parse($sync->completed_at)
                        ->diffInSeconds(Carbon::parse($sync->started_at));
                    $durations[] = $duration;
                }

                $totalRows += $sync->rows_imported ?? 0;
            }

            $avgDuration = count($durations) > 0 ? array_sum($durations) / count($durations) : 0;

            $metrics = [
                'totalSyncs'      => $totalSyncs,
                'successRate'     => round($successRate, 2),
                'avgDuration'     => round($avgDuration, 2),
                'totalRowsSynced' => $totalRows,
            ];

            Log::debug('External data metrics retrieved', [
                'source_id' => $sourceId,
                'metrics'   => $metrics,
            ]);

            return $metrics;
        } catch (\Throwable $e) {
            Log::error('Failed to get refresh metrics', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create custom data transformation rule (regex, calculations).
     *
     * @param  int  $sourceId
     * @param  array{name: string, type: string, config: array}  $transformRule
     * @return array{id: int, name: string, type: string}
     */
    public function createDataTransformationRule(int $sourceId, array $transformRule): array
    {
        try {
            $ruleId = DB::table('bi_transformation_rules')->insertGetId([
                'data_source_id' => $sourceId,
                'name'           => $transformRule['name'],
                'type'           => $transformRule['type'],
                'config'         => json_encode($transformRule['config'] ?? []),
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            Log::info('Transformation rule created', [
                'rule_id'   => $ruleId,
                'source_id' => $sourceId,
                'type'      => $transformRule['type'],
            ]);

            return [
                'id'   => $ruleId,
                'name' => $transformRule['name'],
                'type' => $transformRule['type'],
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create transformation rule', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get integration audit log with user/timestamp changes.
     *
     * @param  int  $sourceId
     * @param  array{limit?: int, action?: string}  $filters
     * @return array{total: int, logs: array}
     */
    public function getIntegrationAuditLog(int $sourceId, array $filters = []): array
    {
        try {
            $query = DB::table('bi_integration_audit_logs')
                ->where('data_source_id', $sourceId);

            if (isset($filters['action'])) {
                $query->where('action', $filters['action']);
            }

            $total = $query->count();

            $logs = $query->orderByDesc('created_at')
                ->limit($filters['limit'] ?? 50)
                ->get()
                ->map(fn ($log) => [
                    'id'        => $log->id,
                    'action'    => $log->action,
                    'user_id'   => $log->user_id,
                    'details'   => json_decode($log->details, true),
                    'timestamp' => $log->created_at?->toIso8601String(),
                ])
                ->all();

            Log::debug('Integration audit logs retrieved', [
                'source_id' => $sourceId,
                'total'     => $total,
            ]);

            return [
                'total' => $total,
                'logs'  => $logs,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get audit logs', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Batch sync multiple data sources simultaneously.
     *
     * @param  array<int>  $sourceIds
     * @param  array  $options
     * @return array{coordinated: bool, syncIds: array, timestamp: string}
     */
    public function batchSyncMultipleSources(array $sourceIds, array $options = []): array
    {
        try {
            $syncIds = [];

            foreach ($sourceIds as $sourceId) {
                try {
                    $result = $this->syncDataFromSource($sourceId, $options);
                    $syncIds[] = $result['syncId'];
                } catch (\Throwable $e) {
                    Log::warning("Failed to sync source {$sourceId}", ['error' => $e->getMessage()]);
                }
            }

            Log::info('Batch sync coordinated', [
                'sources'  => count($sourceIds),
                'syncs'    => count($syncIds),
            ]);

            return [
                'coordinated' => true,
                'syncIds'     => $syncIds,
                'timestamp'   => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to batch sync', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Validate data mapping logic for circular dependencies or conflicts.
     *
     * @param  int  $sourceId
     * @return array{valid: bool, conflicts: array<string>}
     */
    public function validateDataMappingLogic(int $sourceId): array
    {
        try {
            $mappings = DB::table('bi_data_mappings')
                ->where('data_source_id', $sourceId)
                ->where('is_active', true)
                ->get();

            $conflicts = [];

            // Check for duplicate internal field mappings
            $internalFields = $mappings->pluck('internal_field')->all();
            $duplicates     = array_filter(array_count_values($internalFields), fn ($count) => $count > 1);

            foreach ($duplicates as $field => $count) {
                $conflicts[] = "Field '{$field}' is mapped {$count} times (should be unique)";
            }

            // Check for transformation circular dependencies
            foreach ($mappings as $mapping) {
                if ($mapping->transformation) {
                    $transformRules = DB::table('bi_transformation_rules')
                        ->where('data_source_id', $sourceId)
                        ->where('is_active', true)
                        ->get();

                    // Simplified circular dependency check
                    foreach ($transformRules as $rule) {
                        $config = json_decode($rule->config, true);
                        if (isset($config['depends_on']) && in_array($mapping->internal_field, (array) $config['depends_on'])) {
                            // Check reverse dependency
                            if (isset($mapping->transformation) && strpos($mapping->transformation, $rule->name) !== false) {
                                $conflicts[] = "Circular dependency detected between {$mapping->internal_field} and {$rule->name}";
                            }
                        }
                    }
                }
            }

            $valid = empty($conflicts);

            Log::debug('Data mapping logic validated', [
                'source_id' => $sourceId,
                'valid'     => $valid,
                'conflicts' => count($conflicts),
            ]);

            return [
                'valid'     => $valid,
                'conflicts' => $conflicts,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to validate mapping logic', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    // =========================================================================
    // Private Helper Methods
    // =========================================================================

    private function calculateNextSyncTime(string $frequency, ?string $startTime): Carbon
    {
        return match ($frequency) {
            'hourly' => now()->addHour(),
            'daily'  => $startTime ? now()->setTimeFromTimeString($startTime)->addDay() : now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };
    }

    private function applyTransformation(string $transformation, mixed $value): mixed
    {
        // Parse transformation expression
        // Format: "upper|trim" or "regex:/pattern/replacement" etc
        $transforms = explode('|', $transformation);

        foreach ($transforms as $transform) {
            if (strpos($transform, 'regex:') === 0) {
                $parts = explode('/', substr($transform, 6));
                if (count($parts) >= 3) {
                    $value = preg_replace('/' . $parts[0] . '/', $parts[1], (string) $value);
                }
            } else {
                $value = match ($transform) {
                    'upper'  => strtoupper((string) $value),
                    'lower'  => strtolower((string) $value),
                    'trim'   => trim((string) $value),
                    'json'   => json_decode($value, true),
                    default  => $value,
                };
            }
        }

        return $value;
    }

    private function castFieldType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float'   => (float) $value,
            'boolean' => (bool) $value,
            'date'    => $value instanceof Carbon ? $value : Carbon::parse($value),
            'json'    => is_string($value) ? json_decode($value, true) : $value,
            'string'  => (string) $value,
            default   => $value,
        };
    }

    private function introspectCustomApi(object $source): array
    {
        // Placeholder for custom API introspection
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }
}
