<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Setup\Data\TargetSchemas;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportError;
use Modules\Setup\Models\ImportJob;
use RuntimeException;

/**
 * ImportExecutorService
 *
 * Executes the actual data import once all field mappings have been confirmed.
 * Reads from the source (CSV / Excel / PDF / DB), applies per-field transforms,
 * and bulk-inserts rows into the target table with tenant isolation.
 */
class ImportExecutorService
{
    private FileAnalysisService $fileAnalysisService;
    private DatabaseSourceService $dbSourceService;

    public function __construct(
        FileAnalysisService $fileAnalysisService,
        DatabaseSourceService $dbSourceService,
    ) {
        $this->fileAnalysisService = $fileAnalysisService;
        $this->dbSourceService     = $dbSourceService;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Execute the full import for the given job.
     * Updates job status throughout; records per-row errors.
     */
    public function execute(ImportJob $job): void
    {
        $job->update(['status' => 'importing', 'started_at' => now()]);

        try {
            $confirmedMappings = $job->fieldMappings()
                ->where('is_confirmed', true)
                ->get()
                ->all();

            if (empty($confirmedMappings)) {
                throw new RuntimeException('No confirmed mappings found for this job.');
            }

            match ($job->source_type) {
                'csv', 'excel', 'pdf' => $this->executeFileImport($job, $confirmedMappings),
                'database'            => $this->executeDatabaseImport($job, $confirmedMappings),
                default               => throw new RuntimeException("Unsupported source type: {$job->source_type}"),
            };

            $job->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'completed_at'  => now(),
                'error_summary' => ['message' => $e->getMessage()],
            ]);
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Row processing
    // -----------------------------------------------------------------------

    /**
     * Transform a single raw source row into a mapped target row.
     *
     * @param  array<string,mixed>  $rawRow
     * @param  list<FieldMapping>   $confirmedMappings
     * @return array{data: array<string,mixed>, errors: list<array<string,string>>}
     */
    public function processRow(ImportJob $job, array $rawRow, array $confirmedMappings): array
    {
        $data   = [];
        $errors = [];

        foreach ($confirmedMappings as $mapping) {
            $sourceValue = $rawRow[$mapping->source_field] ?? null;

            // Handle required-field check
            if ($mapping->is_required && ($sourceValue === null || trim((string) $sourceValue) === '')) {
                $errors[] = [
                    'field'   => $mapping->target_field,
                    'type'    => 'required_missing',
                    'message' => "Required field '{$mapping->source_field}' is empty.",
                ];
                continue;
            }

            try {
                $data[$mapping->target_field] = $this->applyTransform($sourceValue, $mapping);
            } catch (\Throwable $e) {
                $errors[] = [
                    'field'   => $mapping->target_field,
                    'type'    => 'transform_error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /**
     * Apply the transform defined on a FieldMapping to a single source value.
     */
    public function applyTransform(mixed $value, FieldMapping $mapping): mixed
    {
        if ($value === null) {
            return null;
        }

        $config = $mapping->transform_config ?? [];

        return match ($mapping->transform_type) {
            'direct'        => $value,
            'date_format'   => $this->transformDate((string) $value, $config),
            'number_format' => $this->transformNumber((string) $value, $config),
            'lookup'        => $this->transformLookup((string) $value, $config),
            'concat'        => $this->transformConcat($value, $config),
            'split'         => $this->transformSplit((string) $value, $config),
            'custom'        => $value, // custom transforms are a future extension point
            default         => $value,
        };
    }

    /**
     * Bulk insert a batch of transformed rows into the target table.
     * Automatically adds the table's real tenant column (if any) and timestamps.
     *
     * Chantier 32.10: this used to unconditionally write `tenant_id` on
     * every table regardless of whether that column existed or was the
     * table's real tenant-scoping column — two compounding bugs, confirmed
     * empirically (not just read):
     *  - `acc_invoices`/`acc_chart_of_accounts` have no `tenant_id` column
     *    at all — every real invoice/account import row fatally failed on
     *    "no such column: tenant_id" before this fix.
     *  - `crm_contacts`/`crm_accounts`/`achats_suppliers` DO have a
     *    `tenant_id` column, but it is not what `ContactController`/
     *    `SupplierController` actually filter by — they scope by
     *    `company_id` (see TargetSchemas::TABLES' own docblock). Rows
     *    imported via the old code silently landed with `tenant_id` set and
     *    `company_id` left NULL, making every imported contact/supplier
     *    permanently invisible to the real CRM/Achats list endpoints —
     *    confirmed empirically via a real HTTP round trip (import a
     *    contact, then call `GET crm/contacts` as the same company: 0
     *    results before this fix).
     * Now writes only the real tenant column for the resolved target table
     * (or none, when the table has none) and filters the payload down to
     * columns that actually exist — defense in depth matching the
     * established `Schema::getColumnListing()` filter precedent already
     * used by the sibling `ImportDataJob::bulkInsert()` pipeline.
     *
     * Chantier 32.10: `$tenantId` was typed `int`, but `ImportJob.tenant_id`
     * — what every real call site actually passes — is a real `string(36)`
     * column (matching this whole module's `(string) ($user->company_id
     * ?? 0)` tenant-scoping convention). Under this file's own
     * `declare(strict_types=1)`, that has been a guaranteed fatal
     * `TypeError` on every real invocation of `execute()` since this
     * method was written — confirmed empirically (a real HTTP-dispatched
     * import job, not a direct unit-level call bypassing the type
     * mismatch), not a hypothetical gap. Widened to `int|string` to match
     * what's actually passed.
     *
     * @param  list<array<string,mixed>> $rows
     * @return int Number of rows inserted
     */
    public function insertBatch(string $targetTable, array $rows, int|string $tenantId, ?string $tenantColumn = null): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns = DB::getSchemaBuilder()->getColumnListing($targetTable);
        $now     = now()->toDateTimeString();

        $payload = array_map(function (array $row) use ($columns, $tenantColumn, $tenantId, $now): array {
            $filtered = array_intersect_key($row, array_flip($columns));

            if ($tenantColumn !== null && in_array($tenantColumn, $columns, true)) {
                $filtered[$tenantColumn] = $tenantId;
            }
            if (in_array('created_at', $columns, true)) {
                $filtered['created_at'] = $now;
            }
            if (in_array('updated_at', $columns, true)) {
                $filtered['updated_at'] = $now;
            }

            return $filtered;
        }, $rows);

        DB::table($targetTable)->insert($payload);

        return count($payload);
    }

    /**
     * Record an import error for a specific row.
     */
    public function recordError(
        ImportJob $job,
        int       $rowNumber,
        array     $sourceData,
        string    $field,
        string    $type,
        string    $message
    ): void {
        ImportError::create([
            'import_job_id' => $job->id,
            'row_number'    => $rowNumber,
            'raw_data'      => $sourceData,
            'field_name'    => $field,
            'error_type'    => $type,
            'error_message' => $message,
        ]);

        $job->increment('failed_rows');
    }

    // -----------------------------------------------------------------------
    // Private — source-specific import runners
    // -----------------------------------------------------------------------

    /** @param list<FieldMapping> $confirmedMappings */
    private function executeFileImport(ImportJob $job, array $confirmedMappings): void
    {
        if ($job->source_file_path === null) {
            throw new RuntimeException("ImportJob #{$job->id} has no source_file_path.");
        }

        $disk     = config('setup.storage_disk', 'local');
        $filePath = Storage::disk($disk)->path($job->source_file_path);

        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        // For PDF / Excel fallback we re-use CSV parsing (after analysis)
        $delimiter    = $job->sourceSchema?->detected_delimiter ?? ',';
        $targetTable  = $this->resolveTargetTable($job);
        $tenantColumn = TargetSchemas::tenantColumn($job->target_module, $job->target_entity);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$filePath}");
        }

        // Skip header row
        fgetcsv($handle, 0, $delimiter, '"', '\\');

        $batch      = [];
        $batchSize  = (int) config('setup.batch_size', 500);
        $rowNumber  = 1;
        $imported   = 0;
        $headers    = array_column($job->sourceSchema?->detected_columns ?? [], 'name');

        try {
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $rowNumber++;

                // Map numeric-indexed row to header-keyed array
                $keyed = [];
                foreach ($headers as $i => $header) {
                    $keyed[$header] = $row[$i] ?? null;
                }

                $result = $this->processRow($job, $keyed, $confirmedMappings);

                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $this->recordError($job, $rowNumber, $keyed, $err['field'], $err['type'], $err['message']);
                    }
                    continue;
                }

                $batch[] = $result['data'];

                if (count($batch) >= $batchSize) {
                    $imported += $this->insertBatch($targetTable, $batch, $job->tenant_id, $tenantColumn);
                    $job->update(['imported_rows' => $imported]);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $imported += $this->insertBatch($targetTable, $batch, $job->tenant_id, $tenantColumn);
                $job->update(['imported_rows' => $imported]);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param list<FieldMapping> $confirmedMappings */
    private function executeDatabaseImport(ImportJob $job, array $confirmedMappings): void
    {
        $dbConfig = $job->source_db_config;
        if (empty($dbConfig)) {
            throw new RuntimeException("ImportJob #{$job->id} has no source_db_config.");
        }

        $sourceTable  = $dbConfig['source_table'] ?? $job->target_entity;
        $targetTable  = $this->resolveTargetTable($job);
        $tenantColumn = TargetSchemas::tenantColumn($job->target_module, $job->target_entity);
        $batchSize    = (int) config('setup.batch_size', 500);
        $imported     = 0;

        $total = $this->dbSourceService->streamRows(
            $dbConfig,
            $sourceTable,
            function (array $rows) use ($job, $confirmedMappings, $targetTable, $tenantColumn, $batchSize, &$imported): void {
                $batch     = [];
                $rowNumber = 0;

                foreach ($rows as $rawRow) {
                    $rowNumber++;
                    $result = $this->processRow($job, $rawRow, $confirmedMappings);

                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $err) {
                            $this->recordError($job, $rowNumber, $rawRow, $err['field'], $err['type'], $err['message']);
                        }
                        continue;
                    }

                    $batch[] = $result['data'];
                }

                if (!empty($batch)) {
                    $imported += $this->insertBatch($targetTable, $batch, $job->tenant_id, $tenantColumn);
                    $job->update(['imported_rows' => $imported]);
                }
            },
            $batchSize
        );

        $job->update(['total_rows' => $total]);
    }

    // -----------------------------------------------------------------------
    // Transform implementations
    // -----------------------------------------------------------------------

    /**
     * Convert a date string from source format to Y-m-d.
     *
     * @param array<string,mixed> $config
     */
    private function transformDate(string $value, array $config): ?string
    {
        $sourceFormat = $config['format'] ?? null;

        if ($sourceFormat !== null) {
            try {
                return Carbon::createFromFormat($sourceFormat, trim($value))?->format('Y-m-d');
            } catch (\Throwable) {
                // Fall through to generic parsing
            }
        }

        // Generic date parsing
        $timestamp = strtotime(trim($value));
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        throw new RuntimeException("Cannot parse date value: '{$value}'");
    }

    /**
     * Clean a numeric value: strip currency symbols, handle comma/dot decimal separators.
     *
     * @param array<string,mixed> $config
     */
    private function transformNumber(string $value, array $config): float|int|null
    {
        $cleaned = trim($value);

        // Remove common currency symbols and whitespace
        $cleaned = preg_replace('/[^\d,.\-]/', '', $cleaned) ?? '';

        if ($cleaned === '' || $cleaned === '-') {
            return null;
        }

        // Handle European format: 1.234,56 → 1234.56
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})*(\.\d+)?$/', $cleaned)) {
            // US format: 1,234.56 → 1234.56
            $cleaned = str_replace(',', '', $cleaned);
        } else {
            // Simple comma→dot substitution
            $cleaned = str_replace(',', '.', $cleaned);
        }

        if (!is_numeric($cleaned)) {
            throw new RuntimeException("Cannot parse number value: '{$value}'");
        }

        // Return integer when there's no decimal part
        if (!str_contains($cleaned, '.')) {
            return (int) $cleaned;
        }

        return (float) $cleaned;
    }

    /**
     * Map a value through a lookup dictionary.
     *
     * @param array<string,mixed> $config
     */
    private function transformLookup(string $value, array $config): mixed
    {
        $map          = $config['map']            ?? [];
        $caseSensitive = (bool) ($config['case_sensitive'] ?? false);
        $default      = $config['default']        ?? $value;

        $key = $caseSensitive ? $value : strtolower($value);

        if (!$caseSensitive) {
            $map = array_change_key_case($map, CASE_LOWER);
        }

        return $map[$key] ?? $default;
    }

    /**
     * Concatenate multiple source-field values (value is the primary field value;
     * additional fields are fetched via transform_config.fields — not available here
     * without the full row, so concat with single value is the fallback).
     *
     * @param array<string,mixed> $config
     */
    private function transformConcat(mixed $value, array $config): string
    {
        // When called from processRow with multi-field concat, the caller should
        // pass an array of values. Otherwise treat as single-value pass-through.
        if (is_array($value)) {
            $separator = (string) ($config['separator'] ?? ' ');
            return implode($separator, array_filter(array_map('strval', $value)));
        }

        return (string) $value;
    }

    /**
     * Split a string and return the element at config.index (0-based).
     *
     * @param array<string,mixed> $config
     */
    private function transformSplit(string $value, array $config): string
    {
        $delimiter = (string) ($config['delimiter'] ?? ' ');
        $index     = (int)   ($config['index']     ?? 0);

        $parts = explode($delimiter, $value);

        return trim($parts[$index] ?? '');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Chantier 32.10 (deep 14-layer audit, security — critical): this used
     * to trust `FieldMapping.target_table` outright — a value validated by
     * `SetupController::saveMappings()` as nothing more than
     * `required|string|max:100`, with zero allowlist. Any authenticated
     * user of any tenant able to reach this module (`employee`/`admin`/
     * `super-admin` — a very common role tier) could set `target_table` to
     * ANY real table in the whole application (`users`,
     * `security_encryption_keys`, `core_secrets`, ...) and `target_field`
     * to any column name, and this pipeline would `DB::table($targetTable)
     * ->insert()` straight into it — an arbitrary-table write primitive,
     * confirmed empirically by round-tripping a real HTTP mapping payload
     * with a spoofed `target_table`.
     *
     * The "fallback" derivation (`strtolower(module)_strtolower(entity)`)
     * was ALSO wrong independently — it produced `accounting_invoices`
     * (real table: `acc_invoices`) and `accounting_accounts` (real table:
     * `acc_chart_of_accounts`), and the real frontend
     * (`ImportDataFlow.vue`) never actually sends a real table name in
     * `target_table` at all — it sends the bare entity slug (e.g.
     * `'contacts'`), which resolves to none of this app's real tables. A
     * real end-to-end run confirmed every entity except `products` (which
     * collided by coincidence with the unrelated root-level scaffold
     * `products` table) fatally failed on "table not found" — the real,
     * live onboarding-wizard import flow has never worked, for any entity,
     * since it shipped.
     *
     * Fixed by trusting neither: the destination is now resolved ONLY from
     * the hardcoded `TargetSchemas::table()` allowlist, keyed off the job's
     * own `target_module`/`target_entity` (still free-text at job-creation
     * time — but that's harmless now, since an unrecognised combination
     * simply fails to resolve here rather than ever reaching `DB::table()`).
     */
    private function resolveTargetTable(ImportJob $job): string
    {
        $table = TargetSchemas::table($job->target_module, $job->target_entity);

        if ($table === null) {
            throw new RuntimeException(
                "Unknown import target '{$job->target_module}/{$job->target_entity}' — no real destination table is registered for it."
            );
        }

        return $table;
    }
}
