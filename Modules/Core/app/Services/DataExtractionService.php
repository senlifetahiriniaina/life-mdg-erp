<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ImportJob;

class DataExtractionService
{
    /**
     * Extract data from a CSV file.
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public function extractFromCsv(string $path): array
    {
        $fullPath = Storage::disk('local')->path($path);

        if (! file_exists($fullPath)) {
            return ['headers' => [], 'rows' => []];
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            return ['headers' => [], 'rows' => []];
        }

        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $content)));
        $lines = array_values($lines);

        if (empty($lines)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = str_getcsv(array_shift($lines));
        $headers = array_map('trim', $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $row    = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $values[$i] ?? '';
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Extract data from an XLSX file (falls back to CSV parsing if PhpSpreadsheet not available).
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public function extractFromXlsx(string $path): array
    {
        $fullPath = Storage::disk('local')->path($path);

        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
                $sheet       = $spreadsheet->getActiveSheet();
                $data        = $sheet->toArray(null, true, true, false);

                if (empty($data)) {
                    return ['headers' => [], 'rows' => []];
                }

                $rawHeaders = array_shift($data);
                /** @var list<string> $headers */
                $headers = array_map(fn ($h) => (string) ($h ?? ''), $rawHeaders);

                $rows = [];
                foreach ($data as $rowData) {
                    $row = [];
                    foreach ($headers as $i => $header) {
                        $row[$header] = (string) ($rowData[$i] ?? '');
                    }
                    $rows[] = $row;
                }

                return ['headers' => $headers, 'rows' => $rows];
            } catch (\Throwable) {
                // Fall through to CSV fallback
            }
        }

        // Fallback: try to parse as CSV
        return $this->extractFromCsv($path);
    }

    /**
     * Extract text from a PDF file using pdftotext if available.
     */
    public function extractFromPdf(string $path): string
    {
        $fullPath = Storage::disk('local')->path($path);
        $escaped  = escapeshellarg($fullPath);

        $result = shell_exec("pdftotext -layout {$escaped} -");

        if ($result !== null && trim($result) !== '') {
            return $result;
        }

        // Fallback: base64 encoded content note
        $content = file_get_contents($fullPath);
        if ($content === false) {
            return 'PDF content could not be read.';
        }

        return 'PDF content (base64): ' . base64_encode($content);
    }

    /**
     * Return image content for AI vision processing.
     */
    public function extractFromImage(string $path): string
    {
        $fullPath = Storage::disk('local')->path($path);
        $content  = file_get_contents($fullPath);

        if ($content === false) {
            return 'Image content could not be read.';
        }

        return 'Image content: ' . base64_encode($content);
    }

    /**
     * Dispatch to the correct extraction method based on file_type.
     *
     * @return array<string, mixed>
     */
    public function extract(ImportJob $job): array
    {
        return match ($job->file_type) {
            'csv'        => $this->extractFromCsv($job->file_path),
            'xlsx'       => $this->extractFromXlsx($job->file_path),
            'pdf'        => ['text' => $this->extractFromPdf($job->file_path), 'headers' => [], 'rows' => []],
            'png', 'jpg', 'jpeg' => ['text' => $this->extractFromImage($job->file_path), 'headers' => [], 'rows' => []],
            default      => ['headers' => [], 'rows' => []],
        };
    }
}
