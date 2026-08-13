<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

/**
 * CSV file connector.
 *
 * config keys:
 *   - file_path : absolute path to the CSV file (server-side, after upload)
 *   - delimiter : (optional) defaults to ','
 *   - enclosure : (optional) defaults to '"'
 *   - has_header: (optional) bool, defaults to true
 */
class CsvConnector implements DataSourceConnectorInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function connect(): void
    {
        $path = (string) ($this->config['file_path'] ?? '');
        if ($path === '' || ! file_exists($path)) {
            throw new \RuntimeException("CSV file not found: {$path}");
        }
    }

    public function testConnection(): array
    {
        $start = microtime(true);
        $path  = (string) ($this->config['file_path'] ?? '');

        if ($path === '' || ! file_exists($path)) {
            return ['success' => false, 'latency_ms' => 0, 'message' => "File not found: {$path}"];
        }

        $latency = (int) round((microtime(true) - $start) * 1000);

        return ['success' => true, 'latency_ms' => $latency, 'message' => 'File accessible.'];
    }

    public function fetchData(string $query = ''): array
    {
        $path      = (string) ($this->config['file_path'] ?? '');
        $delimiter = (string) ($this->config['delimiter'] ?? ',');
        $enclosure = (string) ($this->config['enclosure'] ?? '"');
        $hasHeader = (bool) ($this->config['has_header'] ?? true);

        if (! file_exists($path)) {
            throw new \RuntimeException("CSV file not found: {$path}");
        }

        $start  = microtime(true);
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException("Cannot open CSV file: {$path}");
        }

        $columns = [];
        $rows    = [];

        try {
            if ($hasHeader) {
                $headerRow = fgetcsv($handle, 0, $delimiter, $enclosure);
                $columns   = $headerRow !== false ? array_map('strval', $headerRow) : [];
            }

            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
                if ($columns !== []) {
                    $rows[] = array_combine($columns, array_pad($row, count($columns), null));
                } else {
                    $rows[] = $row;
                }
            }
        } finally {
            fclose($handle);
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'duration_ms' => $durationMs,
        ];
    }

    public function getSchema(): array
    {
        $data = $this->fetchData();

        return [
            [
                'table'   => 'csv',
                'columns' => array_map(
                    fn ($col) => ['name' => $col, 'type' => 'string', 'nullable' => true],
                    $data['columns']
                ),
            ],
        ];
    }
}
