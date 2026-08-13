<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

/**
 * DataSourceConnectorInterface — contract for all external data connectors.
 *
 * Each connector wraps a single external data source type (MySQL, PostgreSQL,
 * REST API, CSV, Google Sheets) and provides a uniform API used by
 * DataSourceService to test, fetch, and inspect schemas.
 */
interface DataSourceConnectorInterface
{
    /**
     * Establish (or verify) the connection to the external source.
     *
     * @throws \RuntimeException if the connection cannot be established
     */
    public function connect(): void;

    /**
     * Test whether the connection is alive.
     *
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public function testConnection(): array;

    /**
     * Fetch data from the source using a source-specific query expression.
     *
     * - For SQL sources (mysql, postgresql): `$query` is a SQL SELECT statement.
     * - For REST API: `$query` is a path/endpoint appended to the base URL.
     * - For Google Sheets: `$query` is an A1 range notation (e.g. "Sheet1!A1:Z100").
     * - For CSV: `$query` is ignored (the entire file is returned).
     *
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    public function fetchData(string $query = ''): array;

    /**
     * Discover the schema of the external source.
     *
     * @return array<string, mixed>  Shape depends on connector type:
     *   - SQL: array of {table: string, columns: [{name, type, nullable}]}
     *   - REST API: first-level keys of the response JSON
     *   - Google Sheets: headers (first row) of each detected sheet
     *   - CSV: column headers from the first row
     */
    public function getSchema(): array;
}
