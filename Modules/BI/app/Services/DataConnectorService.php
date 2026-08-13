<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\BI\Models\BiDataSource;

class DataConnectorService
{
    /**
     * Test the connection for a data source.
     */
    public function testConnection(BiDataSource $ds): bool
    {
        try {
            return match ($ds->type) {
                'mysql', 'postgresql' => $this->testDatabaseConnection($ds),
                'google_sheets' => $this->testGoogleSheets($ds),
                'rest_api' => $this->testRestApi($ds),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::warning('BI data source connection test failed', [
                'data_source_id' => $ds->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Run a SQL query against an external data source.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    public function runQuery(BiDataSource $ds, string $sql): array
    {
        if (! in_array($ds->type, ['mysql', 'postgresql'], true)) {
            throw new \RuntimeException('SQL queries are only supported for MySQL and PostgreSQL data sources.');
        }

        $start = microtime(true);
        $pdo = $this->buildPdo($ds);
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $columns = $rows !== [] ? array_keys($rows[0]) : [];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Fetch data from a Google Sheets range.
     *
     * @return array<string, mixed>
     */
    public function fetchGoogleSheetData(BiDataSource $ds, string $range): array
    {
        /** @var array<string, mixed> $config */
        $config = $ds->connection_config;
        $apiKey = (string) ($config['api_key'] ?? '');
        $spreadsheetId = (string) ($config['spreadsheet_id'] ?? '');

        if ($spreadsheetId === '' || $apiKey === '') {
            throw new \RuntimeException('Google Sheets configuration is incomplete (spreadsheet_id and api_key required).');
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/".urlencode($range);

        $response = Http::get($url, ['key' => $apiKey]);
        $response->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Fetch data from a REST API endpoint.
     *
     * @return array<string, mixed>
     */
    public function fetchRestApiData(BiDataSource $ds, string $endpoint): array
    {
        /** @var array<string, mixed> $config */
        $config = $ds->connection_config;
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $authType = (string) ($config['auth_type'] ?? 'none');
        /** @var array<string, string> $headers */
        $headers = (array) ($config['headers'] ?? []);

        $request = Http::withHeaders($headers);

        if ($authType === 'bearer') {
            $request = $request->withToken((string) ($config['token'] ?? ''));
        } elseif ($authType === 'basic') {
            $request = $request->withBasicAuth(
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? '')
            );
        }

        $response = $request->get($baseUrl.'/'.ltrim($endpoint, '/'));
        $response->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $data;
    }

    /**
     * Test a MySQL or PostgreSQL connection.
     */
    private function testDatabaseConnection(BiDataSource $ds): bool
    {
        $pdo = $this->buildPdo($ds);
        $pdo->query('SELECT 1');

        return true;
    }

    /**
     * Build a PDO instance from the data source config.
     */
    private function buildPdo(BiDataSource $ds): \PDO
    {
        /** @var array<string, mixed> $config */
        $config = $ds->connection_config;

        $host = (string) ($config['host'] ?? 'localhost');
        $port = (int) ($config['port'] ?? ($ds->type === 'postgresql' ? 5432 : 3306));
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        $dsn = match ($ds->type) {
            'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            'postgresql' => "pgsql:host={$host};port={$port};dbname={$database}",
            default => throw new \RuntimeException("Unsupported driver: {$ds->type}"),
        };

        return new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    /**
     * Test a Google Sheets connection.
     */
    private function testGoogleSheets(BiDataSource $ds): bool
    {
        /** @var array<string, mixed> $config */
        $config = $ds->connection_config;
        $apiKey = (string) ($config['api_key'] ?? '');
        $spreadsheetId = (string) ($config['spreadsheet_id'] ?? '');

        if ($spreadsheetId === '' || $apiKey === '') {
            return false;
        }

        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}";
        $response = Http::get($url, ['key' => $apiKey]);

        return $response->successful();
    }

    /**
     * Test a REST API connection.
     */
    private function testRestApi(BiDataSource $ds): bool
    {
        /** @var array<string, mixed> $config */
        $config = $ds->connection_config;
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            return false;
        }

        $response = Http::get($baseUrl);

        return $response->successful();
    }
}
