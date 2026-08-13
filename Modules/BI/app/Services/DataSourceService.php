<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Carbon\Carbon;
use Modules\BI\Models\BiDataSource;
use Modules\BI\Services\Connectors\CsvConnector;
use Modules\BI\Services\Connectors\DataSourceConnectorInterface;
use Modules\BI\Services\Connectors\GoogleSheetsConnector;
use Modules\BI\Services\Connectors\MysqlConnector;
use Modules\BI\Services\Connectors\PostgresConnector;
use Modules\BI\Services\Connectors\RestApiConnector;

/**
 * DataSourceService — orchestrates CRUD and connector operations for BiDataSource.
 *
 * Connector registry:
 *   mysql        → MysqlConnector
 *   postgresql   → PostgresConnector
 *   rest_api     → RestApiConnector
 *   csv          → CsvConnector
 *   google_sheets→ GoogleSheetsConnector
 *
 * Connection config is stored encrypted (AES-256 via Laravel's `encrypted:array` cast)
 * using the application key — automatically per-tenant when APP_KEY is tenant-scoped.
 */
class DataSourceService
{
    // -------------------------------------------------------------------------
    // CRUD helpers
    // -------------------------------------------------------------------------

    /**
     * Create a new data source with encrypted connection config.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): BiDataSource
    {
        return BiDataSource::create([
            'name'              => $data['name'],
            'type'              => $data['type'],
            'connection_config' => $data['connection_config'] ?? [],
            'created_by'        => $userId,
            'status'            => 'inactive',
        ]);
    }

    /**
     * Update an existing data source.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BiDataSource $source, array $data): BiDataSource
    {
        $source->update(array_filter([
            'name'              => $data['name'] ?? null,
            'connection_config' => $data['connection_config'] ?? null,
        ], fn ($v) => $v !== null));

        return $source->fresh();
    }

    // -------------------------------------------------------------------------
    // Connection testing
    // -------------------------------------------------------------------------

    /**
     * Test the connection and update the source status accordingly.
     *
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public function testConnection(BiDataSource $source): array
    {
        $connector = $this->resolve($source);
        $result    = $connector->testConnection();

        $source->update([
            'last_tested_at' => Carbon::now(),
            'status'         => $result['success'] ? 'active' : 'error',
        ]);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Data fetching
    // -------------------------------------------------------------------------

    /**
     * Fetch data from an external source using a source-specific query expression.
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    public function fetchData(BiDataSource $source, string $query = ''): array
    {
        $connector = $this->resolve($source);
        $result    = $connector->fetchData($query);

        $source->update(['last_synced_at' => Carbon::now()]);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Schema discovery
    // -------------------------------------------------------------------------

    /**
     * Discover and return the schema of the external data source.
     *
     * @return array<string, mixed>
     */
    public function getSchema(BiDataSource $source): array
    {
        return $this->resolve($source)->getSchema();
    }

    // -------------------------------------------------------------------------
    // Connector registry
    // -------------------------------------------------------------------------

    /**
     * Resolve the appropriate connector for a data source.
     */
    public function resolve(BiDataSource $source): DataSourceConnectorInterface
    {
        /** @var array<string, mixed> $config */
        $config = $source->connection_config ?? [];

        return match ($source->type) {
            'mysql'         => new MysqlConnector($config),
            'postgresql'    => new PostgresConnector($config),
            'rest_api'      => new RestApiConnector($config),
            'csv'           => new CsvConnector($config),
            'google_sheets' => new GoogleSheetsConnector($config),
            default         => throw new \InvalidArgumentException(
                "Unsupported data source type: {$source->type}. "
                . "Supported types: mysql, postgresql, rest_api, csv, google_sheets."
            ),
        };
    }

    /**
     * Return the list of supported connector types.
     *
     * @return list<array{type: string, label: string, requires: list<string>}>
     */
    public function supportedTypes(): array
    {
        return [
            ['type' => 'mysql',         'label' => 'MySQL',         'requires' => ['host', 'database', 'username', 'password']],
            ['type' => 'postgresql',    'label' => 'PostgreSQL',    'requires' => ['host', 'database', 'username', 'password']],
            ['type' => 'rest_api',      'label' => 'REST API',      'requires' => ['base_url']],
            ['type' => 'csv',           'label' => 'CSV File',      'requires' => ['file_path']],
            ['type' => 'google_sheets', 'label' => 'Google Sheets', 'requires' => ['spreadsheet_id', 'api_key']],
        ];
    }
}
