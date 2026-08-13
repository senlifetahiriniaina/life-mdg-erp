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
        $result = [
            'tenant_id'  => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

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
            // Numeric fields — strip spaces and currency symbols
            in_array($target, ['price', 'cost', 'amount', 'salary', 'unit_cost', 'quantity'], true) =>
                $this->normaliseNumeric((string) $value),

            // Date fields
            in_array($target, ['date', 'hire_date'], true) =>
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
        $entityFields = [
            'contacts'  => ['full_name', 'email', 'company'],
            'products'  => ['name', 'sku', 'price'],
            'suppliers' => ['payment_terms', 'currency'],
            'employees' => ['department', 'hire_date', 'salary'],
            'invoices'  => ['number', 'client_name', 'amount'],
            'stock'     => ['product_sku', 'quantity', 'warehouse'],
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
            'invoices'  => 'accounting_invoices',
            'stock'     => 'inventory_stock_movements',
            default     => null,
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

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
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
