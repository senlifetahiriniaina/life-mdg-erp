<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

use Illuminate\Support\Facades\Http;

/**
 * Google Sheets connector via the Sheets REST API v4.
 *
 * config keys:
 *   - spreadsheet_id : Google Sheets document ID
 *   - api_key         : Google API key with Sheets read access
 *   - default_range   : (optional) default A1 range, e.g. "Sheet1!A1:Z1000"
 */
class GoogleSheetsConnector implements DataSourceConnectorInterface
{
    private const BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function connect(): void
    {
        $result = $this->testConnection();
        if (! $result['success']) {
            throw new \RuntimeException($result['message']);
        }
    }

    public function testConnection(): array
    {
        $start         = microtime(true);
        $spreadsheetId = (string) ($this->config['spreadsheet_id'] ?? '');
        $apiKey        = (string) ($this->config['api_key'] ?? '');

        if ($spreadsheetId === '' || $apiKey === '') {
            return ['success' => false, 'latency_ms' => 0, 'message' => 'spreadsheet_id and api_key are required.'];
        }

        try {
            $response = Http::get(self::BASE . "/{$spreadsheetId}", ['key' => $apiKey]);
            $latency  = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'    => $response->successful(),
                'latency_ms' => $latency,
                'message'    => $response->successful() ? 'Connection successful.' : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }

    public function fetchData(string $query = ''): array
    {
        $spreadsheetId = (string) ($this->config['spreadsheet_id'] ?? '');
        $apiKey        = (string) ($this->config['api_key'] ?? '');
        $range         = $query !== '' ? $query : (string) ($this->config['default_range'] ?? 'Sheet1');

        $start    = microtime(true);
        $response = Http::get(
            self::BASE . "/{$spreadsheetId}/values/" . urlencode($range),
            ['key' => $apiKey]
        );
        $response->throw();

        /** @var array<string, mixed> $body */
        $body = $response->json();
        /** @var list<list<string>> $rawRows */
        $rawRows    = $body['values'] ?? [];
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if (empty($rawRows)) {
            return ['columns' => [], 'rows' => [], 'duration_ms' => $durationMs];
        }

        $columns = array_shift($rawRows);
        $rows    = array_map(
            fn ($row) => array_combine(
                $columns,
                array_pad($row, count($columns), null)
            ),
            $rawRows
        );

        return [
            'columns'     => $columns,
            'rows'        => $rows,
            'duration_ms' => $durationMs,
        ];
    }

    public function getSchema(): array
    {
        $spreadsheetId = (string) ($this->config['spreadsheet_id'] ?? '');
        $apiKey        = (string) ($this->config['api_key'] ?? '');

        $meta = Http::get(self::BASE . "/{$spreadsheetId}", ['key' => $apiKey]);
        $meta->throw();

        /** @var array<string, mixed> $body */
        $body   = $meta->json();
        /** @var list<array<string, mixed>> $sheets */
        $sheets = $body['sheets'] ?? [];
        $schema = [];

        foreach ($sheets as $sheet) {
            $title = (string) ($sheet['properties']['title'] ?? 'Sheet1');
            // Fetch header row only
            try {
                $headerData = $this->fetchData("{$title}!1:1");
                $schema[]   = [
                    'table'   => $title,
                    'columns' => array_map(
                        fn ($col) => ['name' => $col, 'type' => 'string', 'nullable' => true],
                        $headerData['columns']
                    ),
                ];
            } catch (\Throwable) {
                $schema[] = ['table' => $title, 'columns' => []];
            }
        }

        return $schema;
    }
}
