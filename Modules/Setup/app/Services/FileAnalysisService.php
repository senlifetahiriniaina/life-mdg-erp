<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Models\SourceSchema;
use RuntimeException;

/**
 * FileAnalysisService
 *
 * Analyses uploaded Excel, CSV and PDF files and persists a SourceSchema
 * record for the given ImportJob.
 */
class FileAnalysisService
{
    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Dispatch to the appropriate analyser based on source_type and persist
     * the resulting SourceSchema.
     */
    public function analyzeFile(ImportJob $job): SourceSchema
    {
        $result = match ($job->source_type) {
            'excel'    => $this->analyzeExcel($job),
            'csv'      => $this->analyzeCsv($job),
            'pdf'      => $this->analyzePdf($job),
            default    => throw new RuntimeException("Unsupported source type: {$job->source_type}"),
        };

        // Upsert SourceSchema (one per job)
        $schema = SourceSchema::updateOrCreate(
            ['import_job_id' => $job->id],
            [
                'detected_columns'   => $result['columns']   ?? [],
                'row_count'          => $result['row_count']  ?? null,
                'sheet_names'        => $result['sheet_names'] ?? null,
                'detected_encoding'  => $result['encoding']   ?? 'UTF-8',
                'detected_delimiter' => $result['delimiter']  ?? null,
            ]
        );

        // Update job total_rows
        $job->update(['total_rows' => $result['row_count'] ?? null]);

        return $schema;
    }

    // -----------------------------------------------------------------------
    // Per-format analysers
    // -----------------------------------------------------------------------

    /**
     * Analyse an Excel file (.xlsx / .xls).
     * Uses PhpSpreadsheet if available; falls back to treating the file as CSV.
     *
     * @return array{columns: list<array<string,mixed>>, row_count: int, sheet_names: list<string>}
     */
    public function analyzeExcel(ImportJob $job): array
    {
        $filePath = $this->resolveFilePath($job);

        // Attempt to use PhpSpreadsheet if it is installed
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet */
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheetNames  = $spreadsheet->getSheetNames();
                $sheet       = $spreadsheet->getActiveSheet();
                $highestRow  = $sheet->getHighestDataRow();
                $highestCol  = $sheet->getHighestDataColumn();

                // Read headers from row 1
                $headers = [];
                $colIndex = 0;
                foreach ($sheet->getRowIterator(1, 1) as $row) {
                    foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                        $headers[$colIndex] = (string) $cell->getValue();
                        $colIndex++;
                    }
                }

                // Collect up to 5 sample rows
                $samples = array_fill(0, count($headers), []);
                $rowNum  = 0;
                foreach ($sheet->getRowIterator(2, min(6, $highestRow)) as $row) {
                    $colIndex = 0;
                    foreach ($row->getCellIterator('A', $highestCol) as $cell) {
                        $samples[$colIndex][] = (string) $cell->getFormattedValue();
                        $colIndex++;
                    }
                    $rowNum++;
                }

                $columns = [];
                foreach ($headers as $i => $header) {
                    $sampleValues = $samples[$i] ?? [];
                    $columns[] = [
                        'name'          => $header,
                        'sample_values' => array_slice($sampleValues, 0, 5),
                        'inferred_type' => $this->inferColumnType($sampleValues),
                    ];
                }

                return [
                    'columns'    => $columns,
                    'row_count'  => $highestRow > 1 ? $highestRow - 1 : 0,
                    'sheet_names'=> $sheetNames,
                ];
            } catch (\Throwable) {
                // Fall through to CSV fallback
            }
        }

        // Fallback: treat as CSV (works for many simple .xls exports)
        return $this->analyzeCsv($job);
    }

    /**
     * Analyse a CSV file.
     * Auto-detects delimiter (comma, semicolon, tab); reads first 200 rows.
     *
     * @return array{columns: list<array<string,mixed>>, row_count: int, delimiter: string, encoding: string}
     */
    public function analyzeCsv(ImportJob $job): array
    {
        $filePath  = $this->resolveFilePath($job);
        $handle    = fopen($filePath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$filePath}");
        }

        try {
            // Detect encoding
            $rawBytes = fread($handle, 4096);
            $encoding = $this->detectEncoding((string) $rawBytes);
            rewind($handle);

            // Detect delimiter using first line
            $firstLine = (string) fgets($handle);
            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            // Parse headers
            $headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if ($headers === false || $headers === null) {
                throw new RuntimeException('Cannot parse CSV headers.');
            }
            $headers = array_map('strval', $headers);

            // Collect samples (up to 200 data rows, store up to 5 samples per column)
            $samples  = array_fill(0, count($headers), []);
            $rowCount = 0;

            while ($rowCount < 200) {
                $row = fgetcsv($handle, 0, $delimiter, '"', '\\');
                if ($row === false) {
                    break;
                }
                foreach ($headers as $i => $header) {
                    if (isset($row[$i]) && count($samples[$i]) < 5) {
                        $val = trim((string) $row[$i]);
                        if ($val !== '') {
                            $samples[$i][] = $val;
                        }
                    }
                }
                $rowCount++;
            }

            // Count remaining rows
            while (fgetcsv($handle, 0, $delimiter, '"', '\\') !== false) {
                $rowCount++;
            }

            $columns = [];
            foreach ($headers as $i => $header) {
                $columns[] = [
                    'name'          => $header,
                    'sample_values' => $samples[$i] ?? [],
                    'inferred_type' => $this->inferColumnType($samples[$i] ?? []),
                ];
            }

            return [
                'columns'   => $columns,
                'row_count' => $rowCount,
                'delimiter' => $delimiter,
                'encoding'  => $encoding,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Analyse a PDF file via text extraction.
     * Looks for table-like structures (lines with consistent separators).
     * Best-effort; AI mapping is recommended for PDFs.
     *
     * @return array{columns: list<array<string,mixed>>, row_count: int, encoding: string}
     */
    public function analyzePdf(ImportJob $job): array
    {
        $filePath = $this->resolveFilePath($job);

        $text = $this->extractPdfText($filePath);

        // Split into lines and look for lines with consistent separators
        $lines     = array_filter(array_map('trim', explode("\n", $text)));
        $tableLines = [];

        foreach ($lines as $line) {
            // Detect table-like lines: multiple tab/pipe/multi-space separators
            if (preg_match('/(\t|\|{1,2}|  {2,})/', $line)) {
                $tableLines[] = $line;
            }
        }

        // Use up to first 10 table-like lines for header detection
        $columns  = [];
        $rowCount = max(0, count($tableLines) - 1);

        if (!empty($tableLines)) {
            $headerLine = $tableLines[0];
            // Split on tabs, pipes or 2+ spaces
            $headers = preg_split('/\t|\|+|\s{2,}/', $headerLine);
            $headers = array_filter(array_map('trim', $headers ?: []));

            // Collect sample values from subsequent lines
            $samples = array_fill(0, count($headers), []);
            foreach (array_slice($tableLines, 1, 5) as $dataLine) {
                $cells = preg_split('/\t|\|+|\s{2,}/', $dataLine);
                $cells = array_values(array_map('trim', $cells ?: []));
                foreach (array_values(array_keys(iterator_to_array((function () use ($headers) {
                    foreach ($headers as $k => $v) {
                        yield $k => $v;
                    }
                })())) ) as $i) {
                    if (isset($cells[$i]) && $cells[$i] !== '' && count($samples[$i]) < 5) {
                        $samples[$i][] = $cells[$i];
                    }
                }
            }

            foreach (array_values($headers) as $i => $header) {
                $columns[] = [
                    'name'          => $header,
                    'sample_values' => $samples[$i] ?? [],
                    'inferred_type' => $this->inferColumnType($samples[$i] ?? []),
                    'note'          => 'PDF extraction — AI mapping recommended',
                ];
            }
        }

        // If no table structure found, return a single "raw_text" column
        if (empty($columns)) {
            $columns = [[
                'name'          => 'raw_text',
                'sample_values' => array_slice(array_values($lines), 0, 5),
                'inferred_type' => 'string',
                'note'          => 'No table structure detected — manual mapping required',
            ]];
        }

        return [
            'columns'   => $columns,
            'row_count' => $rowCount,
            'encoding'  => 'UTF-8',
        ];
    }

    // -----------------------------------------------------------------------
    // Type inference
    // -----------------------------------------------------------------------

    /**
     * Infer the semantic type of a column from a list of sample values.
     *
     * Returns one of: 'date'|'decimal'|'integer'|'boolean'|'email'|'phone'|'string'
     */
    public function inferColumnType(array $samples): string
    {
        if (empty($samples)) {
            return 'string';
        }

        $nonEmpty = array_filter($samples, fn ($v) => trim((string) $v) !== '');
        if (empty($nonEmpty)) {
            return 'string';
        }

        $scores = ['date' => 0, 'decimal' => 0, 'integer' => 0, 'boolean' => 0, 'email' => 0, 'phone' => 0];

        foreach ($nonEmpty as $value) {
            $v = trim((string) $value);

            // Email
            if (filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $scores['email']++;
                continue;
            }

            // Boolean
            if (in_array(strtolower($v), ['true', 'false', 'yes', 'no', 'oui', 'non', '1', '0', 'y', 'n'], true)) {
                $scores['boolean']++;
                continue;
            }

            // Date patterns
            if (preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $v)
                || preg_match('/^\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2}$/', $v)
                || strtotime($v) !== false && preg_match('/\d{4}/', $v)
            ) {
                $scores['date']++;
                continue;
            }

            // Phone: starts with +, or is all digits/spaces/dashes with 7+ chars
            if (preg_match('/^\+?[\d\s\-\(\)\.]{7,20}$/', $v) && preg_match('/\d{7,}/', preg_replace('/\D/', '', $v))) {
                $scores['phone']++;
                continue;
            }

            // Decimal (has comma or dot as decimal separator)
            $cleaned = str_replace([' ', "\xc2\xa0"], '', $v); // remove spaces / NBSP
            $cleaned = preg_replace('/[^\d,\.\-]/', '', $cleaned) ?? '';
            $normalized = str_replace(',', '.', $cleaned);
            if (preg_match('/^-?\d+\.\d+$/', $normalized)) {
                $scores['decimal']++;
                continue;
            }

            // Integer
            if (preg_match('/^-?\d+$/', str_replace([' ', "\xc2\xa0"], '', $v))) {
                $scores['integer']++;
                continue;
            }
        }

        arsort($scores);
        $topType  = array_key_first($scores);
        $topScore = $scores[$topType] ?? 0;
        $total    = count($nonEmpty);

        // Require at least 60% consensus
        if ($topScore / $total >= 0.6) {
            return $topType;
        }

        return 'string';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function resolveFilePath(ImportJob $job): string
    {
        $disk = config('setup.storage_disk', 'local');

        if ($job->source_file_path === null) {
            throw new RuntimeException("ImportJob #{$job->id} has no source_file_path.");
        }

        $path = Storage::disk($disk)->path($job->source_file_path);

        if (!file_exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $path;
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts     = [];

        foreach ($delimiters as $d) {
            $counts[$d] = substr_count($line, $d);
        }

        arsort($counts);
        $best = array_key_first($counts);

        // Default to comma when no clear winner
        return ($counts[$best] > 0) ? (string) $best : ',';
    }

    private function detectEncoding(string $sample): string
    {
        // BOM detection
        if (str_starts_with($sample, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        if (str_starts_with($sample, "\xFF\xFE") || str_starts_with($sample, "\xFE\xFF")) {
            return 'UTF-16';
        }

        if (function_exists('mb_detect_encoding')) {
            $detected = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'UTF-16'], true);
            return $detected !== false ? $detected : 'UTF-8';
        }

        return 'UTF-8';
    }

    /**
     * Attempt to extract text content from a PDF.
     * Uses pdftotext (poppler) if available; otherwise reads raw bytes looking for text streams.
     */
    private function extractPdfText(string $filePath): string
    {
        // Try pdftotext (poppler-utils, often available on Linux servers)
        if (function_exists('exec') && trim((string) shell_exec('which pdftotext')) !== '') {
            $escaped  = escapeshellarg($filePath);
            $output   = shell_exec("pdftotext -layout {$escaped} -");
            if ($output !== null && $output !== '') {
                return $output;
            }
        }

        // Fallback: read raw bytes and extract text-like sequences
        $raw  = file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }

        // Extract text between BT...ET markers (PDF text objects)
        $text = '';
        if (preg_match_all('/BT(.*?)ET/s', $raw, $matches)) {
            foreach ($matches[1] as $block) {
                // Extract strings from parentheses
                if (preg_match_all('/\(([^)]+)\)/', $block, $strings)) {
                    $text .= implode(' ', $strings[1]) . "\n";
                }
            }
        }

        return $text !== '' ? $text : $raw;
    }
}
