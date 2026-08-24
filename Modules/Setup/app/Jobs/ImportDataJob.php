<?php

declare(strict_types=1);

namespace Modules\Setup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ImportDataJob
 *
 * Queued background job that executes the full AI-assisted data import pipeline.
 *
 * Pipeline:
 *  1. Open file and count rows
 *  2. Read in chunks of 100 rows
 *  3. Transform each row per mapping
 *  4. Bulk-insert into the correct tenant table
 *  5. Update progress in cache (for polling via getImportStatus)
 *  6. Report final statistics
 *
 * Progress is stored in Laravel cache under key "import_job:{jobId}".
 * Status transitions: queued → processing → completed | failed
 *
 * Africa First: supports OHADA entity tables, XOF currency normalisation.
 */
class ImportDataJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Rows processed per chunk to balance memory and DB round-trips */
    private const CHUNK_SIZE = 100;

    /** Maximum retries before the job is considered failed */
    public int $tries = 2;

    /** Timeout in seconds (30 min for very large files) */
    public int $timeout = 1800;

    public function __construct(
        public readonly string $jobId,
        public readonly string $filePath,
        /** @var list<array{source: string, target: string}> */
        public readonly array  $mapping,
        public readonly string $tenantId,
    ) {}

    public function handle(): void
    {
        $this->updateStatus('processing', 0);

        try {
            $this->runImport();
        } catch (Throwable $e) {
            Log::error('ImportDataJob failed', [
                'job_id' => $this->jobId,
                'error'  => $e->getMessage(),
            ]);
            $this->failWithError($e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        $this->failWithError($e->getMessage());
    }

    // -------------------------------------------------------------------------
    // Core import pipeline
    // -------------------------------------------------------------------------

    private function runImport(): void
    {
        $ext = strtolower(pathinfo($this->filePath, PATHINFO_EXTENSION));

        $totalRows = $this->countRows($ext);
        $imported  = 0;
        $skipped   = 0;
        $errors    = [];

        $iterator = $this->rowIterator($ext);
        $chunk    = [];

        foreach ($iterator as $index => $row) {
            $transformed = $this->transformRow($row);

            if ($transformed === null) {
                $skipped++;
            } else {
                $chunk[] = $transformed;
            }

            if (count($chunk) >= self::CHUNK_SIZE) {
                [$ins, $sk, $err] = $this->bulkInsert($chunk);
                $imported += $ins;
                $skipped  += $sk;
                $errors    = array_merge($errors, $err);
                $chunk     = [];
            }

            // Update progress every 50 rows
            if ($index % 50 === 0 && $totalRows > 0) {
                $progress = (int) min(99, round(($index / $totalRows) * 100));
                $this->updateStatus('processing', $progress, $imported, $skipped, $errors);
            }
        }

        // Flush remaining chunk
        if (! empty($chunk)) {
            [$ins, $sk, $err] = $this->bulkInsert($chunk);
            $imported += $ins;
            $skipped  += $sk;
            $errors    = array_merge($errors, $err);
        }

        $this->updateStatus('completed', 100, $imported, $skipped, $errors);
    }

    // -------------------------------------------------------------------------
    // Row transformation
    // -------------------------------------------------------------------------

    /**
     * Transform a source row per the mapping config.
     * Returns null if the row should be skipped (all values empty).
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function transformRow(array $row): ?array
    {
        $result = [];

        $hasValue = false;

        foreach ($this->mapping as $map) {
            $source = $map['source'] ?? '';
            $target = $map['target'] ?? '';

            if ($target === '' || $target === 'ignore') {
                continue;
            }

            $value = $row[$source] ?? null;

            if ($value !== null && $value !== '') {
                $hasValue = true;
            }

            // Chantier 12: 'full_name' is a mapping-UI concept only — no
            // target table (crm_contacts, hr_employees) has a full_name
            // column, so bulkInsert()'s column-intersection filter silently
            // dropped it on every import (the name was lost with no error).
            // Split into the real columns instead of writing the phantom key.
            if ($target === 'full_name') {
                $normalised = $this->normaliseValue($target, $value);
                [$firstName, $lastName] = $this->splitFullName((string) ($normalised ?? ''));
                $result['first_name'] = $firstName;
                $result['last_name']  = $lastName;
                continue;
            }

            // Chantier 32.10: `inventory_products` carries both `sale_price`
            // and `selling_price` as the real, kept-in-sync price columns
            // (see Modules\Inventory\Http\Controllers\Api\ProductController
            // ::store()'s own sync logic) — mirror that here so a product
            // imported via this pipeline displays correctly everywhere the
            // rest of the app reads either column, not just one of them.
            if ($target === 'selling_price') {
                $normalised = $this->normaliseValue($target, $value);
                $result['selling_price'] = $normalised;
                $result['sale_price']    = $normalised;
                continue;
            }

            $result[$target] = $this->normaliseValue($target, $value);
        }

        return $hasValue ? $result : null;
    }

    /**
     * Normalise a value for the target field.
     * Handles currency, date, phone number normalization.
     *
     * @param  mixed $value
     * @return mixed
     */
    private function normaliseValue(string $target, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = is_string($value) ? trim($value) : $value;

        return match (true) {
            // Numeric fields — strip spaces and currency symbols.
            // Chantier 32.10: kept in sync with ENTITY_SCHEMAS' real target
            // field names ('price'/'cost'/'amount' were never real columns
            // on any destination table — see AiDataImportService's own
            // docblock).
            in_array($target, ['selling_price', 'cost_price', 'total', 'subtotal', 'tax_amount'], true) =>
                $this->normaliseNumeric((string) $value),

            // Date fields — 'invoice_date' replaces the old, never-real 'date'.
            in_array($target, ['invoice_date', 'due_date', 'hire_date'], true) =>
                $this->normaliseDate((string) $value),

            // Phone — strip spaces but keep +
            $target === 'phone' =>
                preg_replace('/[^\d+\-()]/', '', (string) $value),

            // Currency — uppercase, default XOF
            $target === 'currency' =>
                strtoupper(trim((string) $value)) ?: 'XOF',

            default => $value,
        };
    }

    /**
     * Split a single "full name" cell into [first_name, last_name] — the
     * shape crm_contacts/hr_employees actually store. A name with no space
     * (or empty) becomes [name, ''] rather than dropping data outright.
     *
     * @return array{0: string, 1: string}
     */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim($fullName);

        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function normaliseNumeric(string $value): float|null
    {
        // Remove currency symbols, spaces, then parse
        $clean = preg_replace('/[^0-9.,\-]/', '', $value);
        $clean = str_replace(',', '.', (string) $clean);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function normaliseDate(string $value): string|null
    {
        if ($value === '') {
            return null;
        }

        // Try common date formats
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt->format('Y-m-d');
            }
        }

        // Last resort: strtotime
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    // -------------------------------------------------------------------------
    // Bulk insert
    // -------------------------------------------------------------------------

    /**
     * Insert a chunk into the appropriate tenant table.
     *
     * @param  list<array<string, mixed>> $rows
     * @return array{int, int, list<string>} [inserted, skipped, errors]
     */
    private function bulkInsert(array $rows): array
    {
        $entity = $this->detectEntityFromMapping();
        $table  = $this->entityToTable($entity);

        if ($table === null) {
            return [0, count($rows), ["Table inconnue pour l'entité \"{$entity}\""]];
        }

        // Not every target table has the same columns as ENTITY_SCHEMAS assumes
        // (e.g. acc_invoices has no tenant_id column at all) — filter each
        // row down to columns that actually exist rather than let one
        // unknown column fail the whole insert.
        //
        // Chantier 32.10: the tenant column itself used to be a blanket
        // "write tenant_id whenever that column exists" — but confirmed
        // empirically that `crm_contacts`/`achats_suppliers` are NOT
        // actually scoped by `tenant_id` by their own real controllers
        // (`ContactController`/`SupplierController` both filter by
        // `company_id`, per this class's own `entityTenantColumn()`
        // docblock) — every contact/supplier imported through this
        // pipeline landed with `tenant_id` set and `company_id` left NULL,
        // making them permanently invisible to the real CRM/Achats list
        // endpoints (confirmed via a real HTTP round trip: import a
        // contact, then `GET crm/contacts` as the same company → 0
        // results, before this fix).
        $columns      = DB::getSchemaBuilder()->getColumnListing($table);
        $tenantColumn = $this->entityTenantColumn($entity);
        $rows         = array_map(function (array $row) use ($columns, $tenantColumn): array {
            $filtered = array_intersect_key($row, array_flip($columns));

            if ($tenantColumn !== null && in_array($tenantColumn, $columns, true)) {
                $filtered[$tenantColumn] = $this->tenantId;
            }
            if (in_array('created_at', $columns, true)) {
                $filtered['created_at'] = now();
            }
            if (in_array('updated_at', $columns, true)) {
                $filtered['updated_at'] = now();
            }

            return $filtered;
        }, $rows);

        try {
            DB::table($table)->insert($rows);
            return [count($rows), 0, []];
        } catch (Throwable $e) {
            // Fallback: insert one by one to collect per-row errors
            $inserted = 0;
            $skipped  = 0;
            $errors   = [];

            foreach ($rows as $idx => $row) {
                try {
                    DB::table($table)->insert($row);
                    $inserted++;
                } catch (Throwable $rowErr) {
                    $skipped++;
                    $errors[] = "Ligne {$idx}: " . $rowErr->getMessage();
                }
            }

            return [$inserted, $skipped, $errors];
        }
    }

    private function detectEntityFromMapping(): string
    {
        $targets = array_column($this->mapping, 'target');
        // Chantier 32.10: field lists kept in sync with
        // AiDataImportService::ENTITY_SCHEMAS' real target field names —
        // 'company'/'price'/'department'/'salary'/'client_name'/'amount'
        // were never real columns on any destination table (see that
        // class's own docblock); 'stock' removed entirely (superseded by
        // the real Chantier 16 StockImportService feature).
        $entityFields = [
            'contacts'  => ['full_name', 'email'],
            'products'  => ['name', 'sku', 'selling_price'],
            'suppliers' => ['payment_terms', 'currency'],
            'employees' => ['job_title', 'hire_date'],
            'invoices'  => ['number', 'partner_name', 'total'],
        ];

        $best       = 'contacts';
        $bestScore  = 0;

        foreach ($entityFields as $entity => $fields) {
            $score = count(array_intersect($targets, $fields));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $entity;
            }
        }

        return $best;
    }

    private function entityToTable(string $entity): ?string
    {
        return match ($entity) {
            'contacts'  => 'crm_contacts',
            'products'  => 'inventory_products',
            'suppliers' => 'achats_suppliers',
            'employees' => 'hr_employees',
            'invoices'  => 'acc_invoices',
            default     => null,
        };
    }

    /**
     * Real tenant/company-scoping column per entity, matching each real
     * table's own real controller (see this method's mention in
     * `bulkInsert()`'s docblock for the full investigation). `null` means
     * the entity's destination table has no tenant/company column at all
     * (acc_invoices — this app's shared-ledger design).
     */
    private function entityTenantColumn(string $entity): ?string
    {
        return match ($entity) {
            'contacts', 'suppliers' => 'company_id',
            'products', 'employees' => 'tenant_id',
            default                 => null,
        };
    }

    // -------------------------------------------------------------------------
    // File iteration
    // -------------------------------------------------------------------------

    private function countRows(string $ext): int
    {
        if (! file_exists($this->filePath)) {
            return 0;
        }

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->filePath);
                    return max(0, $spreadsheet->getActiveSheet()->getHighestDataRow() - 1);
                } catch (\Throwable) {
                    // fallthrough
                }
            }
        }

        $count = 0;
        $handle = fopen($this->filePath, 'r');
        if ($handle) {
            while (fgets($handle) !== false) {
                $count++;
            }
            fclose($handle);
        }

        return max(0, $count - 1);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function rowIterator(string $ext): iterable
    {
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            yield from $this->excelIterator();
        } else {
            yield from $this->csvIterator();
        }
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function csvIterator(): iterable
    {
        if (! file_exists($this->filePath)) {
            return;
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            return;
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = str_contains((string) $firstLine, ';') ? ';' : ',';

        $headers = null;
        $index   = 0;

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            yield $index => array_combine($headers, array_pad($row, count($headers), null));
            $index++;
        }

        fclose($handle);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function excelIterator(): iterable
    {
        if (! class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            yield from $this->csvIterator();
            return;
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->filePath);
            $sheet       = $spreadsheet->getActiveSheet();
            $highestRow  = $sheet->getHighestDataRow();
            $highestCol  = $sheet->getHighestDataColumn();

            $headers = [];
            foreach (range('A', $highestCol) as $col) {
                $val = (string) $sheet->getCell("{$col}1")->getValue();
                if ($val !== '') {
                    $headers[] = $val;
                }
            }

            for ($rowNum = 2; $rowNum <= $highestRow; $rowNum++) {
                $row = [];
                foreach ($headers as $idx => $header) {
                    $col       = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                    $row[$header] = $sheet->getCell("{$col}{$rowNum}")->getFormattedValue();
                }
                yield ($rowNum - 2) => $row;
            }
        } catch (\Throwable) {
            yield from $this->csvIterator();
        }
    }

    // -------------------------------------------------------------------------
    // Cache helpers
    // -------------------------------------------------------------------------

    private function updateStatus(
        string $status,
        int $progress,
        int $imported = 0,
        int $skipped  = 0,
        array $errors = [],
    ): void {
        Cache::put("import_job:{$this->jobId}", [
            'status'   => $status,
            'progress' => $progress,
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 50), // cap at 50 errors
        ], now()->addHours(24));
    }

    private function failWithError(string $message): void
    {
        $current = Cache::get("import_job:{$this->jobId}", []);

        Cache::put("import_job:{$this->jobId}", array_merge($current, [
            'status'  => 'failed',
            'errors'  => array_merge($current['errors'] ?? [], [$message]),
        ]), now()->addHours(24));
    }
}
