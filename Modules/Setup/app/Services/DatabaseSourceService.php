<?php

declare(strict_types=1);

namespace Modules\Setup\Services;

use Illuminate\Support\Facades\Crypt;
use PDO;
use PDOException;
use RuntimeException;

/**
 * DatabaseSourceService
 *
 * Connects read-only to external ERP databases for data migration.
 * Supports MySQL, PostgreSQL, MSSQL and SQLite source systems.
 *
 * Africa First / Asia First: includes patterns for common ERPs used in
 * francophone African, Sub-Saharan and Asian markets.
 */
class DatabaseSourceService
{
    /**
     * Known source ERP systems — maps alias → driver hint.
     * Used to pre-populate the UI connection form.
     */
    public const KNOWN_ERPS = [
        'sage_compta' => ['driver' => 'sqlsrv', 'hint' => 'Sage Comptabilité / SAARI'],
        'cegid'       => ['driver' => 'sqlsrv', 'hint' => 'Cegid / Quadra'],
        'ebp'         => ['driver' => 'sqlsrv', 'hint' => 'EBP Compta'],
        'odoo'        => ['driver' => 'pgsql',  'hint' => 'Odoo / OpenERP'],
        'quickbooks'  => ['driver' => 'mysql',  'hint' => 'QuickBooks (via connector)'],
        'tally'       => ['driver' => 'mysql',  'hint' => 'Tally ERP (India)'],
        'kingdee'     => ['driver' => 'sqlsrv', 'hint' => 'Kingdee / 金蝶 (China)'],
        'yonyou'      => ['driver' => 'sqlsrv', 'hint' => 'Yonyou / 用友 (China)'],
        'generic'     => ['driver' => 'mysql',  'hint' => 'Generic MySQL/PostgreSQL'],
    ];

    // -----------------------------------------------------------------------
    // Connection helpers
    // -----------------------------------------------------------------------

    /**
     * Test whether a connection can be established with the given config.
     *
     * @param  array{driver: string, host: string, port?: int, database: string, username: string, password: string} $config
     * @throws RuntimeException with a descriptive message on failure
     */
    public function testConnection(array $config): bool
    {
        try {
            $pdo = $this->buildPdo($config);
            $pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $this->sanitizeErrorMessage($e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Return all non-system table names from the source database.
     *
     * @param  array<string,mixed> $config
     * @return list<string>
     */
    public function listTables(array $config): array
    {
        $pdo    = $this->buildPdo($config);
        $driver = strtolower($config['driver'] ?? 'mysql');

        $sql = match ($driver) {
            'pgsql'  => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name",
            'sqlsrv' => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name",
            default  => "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME",
        };

        $stmt   = $pdo->query($sql);
        $rows   = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        return array_values(array_map('strval', $rows));
    }

    /**
     * Return column information and up to 5 sample values for a given table.
     *
     * @param  array<string,mixed> $config
     * @return list<array{column: string, type: string, samples: list<mixed>}>
     */
    public function getTableSchema(array $config, string $table): array
    {
        $pdo    = $this->buildPdo($config);
        $driver = strtolower($config['driver'] ?? 'mysql');

        // Fetch column names + types
        $columns = $this->fetchColumns($pdo, $driver, $table, $config['database'] ?? '');

        // Fetch sample rows (up to 5)
        $quotedTable = $this->quoteIdentifier($pdo, $table, $driver);
        $sampleStmt  = $pdo->query("SELECT * FROM {$quotedTable} LIMIT 5");
        $sampleRows  = $sampleStmt ? $sampleStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $result = [];
        foreach ($columns as ['column' => $col, 'type' => $type]) {
            $samples = array_values(array_map(
                fn ($row) => $row[$col] ?? null,
                $sampleRows
            ));
            $result[] = [
                'column'  => $col,
                'type'    => $type,
                'samples' => array_slice($samples, 0, 5),
            ];
        }

        return $result;
    }

    /**
     * Stream rows from a source table in batches, calling $callback for each batch.
     * Returns the total number of rows processed.
     *
     * @param  array<string,mixed> $config
     * @param  callable(list<array<string,mixed>>): void $callback
     */
    public function streamRows(array $config, string $table, callable $callback, int $batchSize = 500): int
    {
        $pdo         = $this->buildPdo($config);
        $driver      = strtolower($config['driver'] ?? 'mysql');
        $quotedTable = $this->quoteIdentifier($pdo, $table, $driver);

        $offset = 0;
        $total  = 0;

        while (true) {
            $sql  = $this->buildPagedQuery($driver, $quotedTable, $batchSize, $offset);
            $stmt = $pdo->query($sql);

            if ($stmt === false) {
                break;
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                break;
            }

            $callback($rows);

            $total  += count($rows);
            $offset += $batchSize;

            if (count($rows) < $batchSize) {
                break; // last page
            }
        }

        return $total;
    }

    // -----------------------------------------------------------------------
    // Encryption helpers
    // -----------------------------------------------------------------------

    /**
     * AES encrypt a config array before storing in the database.
     */
    public function encryptConfig(array $config): string
    {
        return Crypt::encryptString(json_encode($config, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt a previously encrypted config string.
     *
     * @return array<string,mixed>
     */
    public function decryptConfig(string $encrypted): array
    {
        $json = Crypt::decryptString($encrypted);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $config
     */
    private function buildPdo(array $config): PDO
    {
        $driver   = strtolower($config['driver'] ?? 'mysql');
        $host     = $config['host']     ?? '127.0.0.1';
        $port     = (int) ($config['port'] ?? $this->defaultPort($driver));
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        $dsn = match ($driver) {
            'pgsql'  => "pgsql:host={$host};port={$port};dbname={$database}",
            'sqlsrv' => "sqlsrv:Server={$host},{$port};Database={$database}",
            'sqlite' => "sqlite:{$database}",
            default  => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        };

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ];

        return new PDO($dsn, $username, $password, $options);
    }

    private function defaultPort(string $driver): int
    {
        return match ($driver) {
            'pgsql'  => 5432,
            'sqlsrv' => 1433,
            default  => 3306,
        };
    }

    /**
     * @return list<array{column: string, type: string}>
     */
    private function fetchColumns(PDO $pdo, string $driver, string $table, string $database): array
    {
        $sql = match ($driver) {
            'pgsql'  => "SELECT column_name AS col, data_type AS typ FROM information_schema.columns WHERE table_name = :table ORDER BY ordinal_position",
            'sqlsrv' => "SELECT COLUMN_NAME AS col, DATA_TYPE AS typ FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table ORDER BY ORDINAL_POSITION",
            'sqlite' => "PRAGMA table_info(" . $this->quoteIdentifier($pdo, $table, $driver) . ")",
            default  => "SELECT COLUMN_NAME AS col, DATA_TYPE AS typ FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION",
        };

        if ($driver === 'sqlite') {
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return array_map(fn ($r) => ['column' => (string) $r['name'], 'type' => (string) $r['type']], $rows);
        }

        $stmt = $pdo->prepare($sql);
        $params = ['table' => $table];
        if ($driver === 'mysql') {
            $params['database'] = $database;
        }
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn ($r) => ['column' => (string) ($r['col'] ?? ''), 'type' => (string) ($r['typ'] ?? '')], $rows);
    }

    private function quoteIdentifier(PDO $pdo, string $identifier, string $driver): string
    {
        $identifier = str_replace(['`', '"', '[', ']'], '', $identifier);

        return match ($driver) {
            'pgsql'  => '"' . $identifier . '"',
            'sqlsrv' => '[' . $identifier . ']',
            default  => '`' . $identifier . '`',
        };
    }

    private function buildPagedQuery(string $driver, string $quotedTable, int $limit, int $offset): string
    {
        return match ($driver) {
            'sqlsrv' => "SELECT * FROM {$quotedTable} ORDER BY (SELECT NULL) OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY",
            default  => "SELECT * FROM {$quotedTable} LIMIT {$limit} OFFSET {$offset}",
        };
    }

    private function sanitizeErrorMessage(string $message): string
    {
        // Strip potential credentials from error messages before surfacing to users
        return preg_replace('/password[=\s:]+\S+/i', 'password=[REDACTED]', $message) ?? $message;
    }
}
