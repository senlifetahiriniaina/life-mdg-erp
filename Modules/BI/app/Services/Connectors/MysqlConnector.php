<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

/**
 * MySQL external data source connector.
 *
 * Connects to an external (non-ERP) MySQL database via PDO using the
 * configuration stored (encrypted) on the BiDataSource model.
 */
class MysqlConnector implements DataSourceConnectorInterface
{
    private ?\PDO $pdo = null;

    /**
     * @param  array<string, mixed>  $config  Decrypted connection_config
     */
    public function __construct(private readonly array $config) {}

    public function connect(): void
    {
        $this->pdo = $this->buildPdo();
    }

    public function testConnection(): array
    {
        $start = microtime(true);
        try {
            $pdo = $this->buildPdo();
            $pdo->query('SELECT 1');
            $latency = (int) round((microtime(true) - $start) * 1000);

            return ['success' => true, 'latency_ms' => $latency, 'message' => 'Connection successful.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'latency_ms' => 0, 'message' => $e->getMessage()];
        }
    }

    public function fetchData(string $query = ''): array
    {
        if ($query === '') {
            throw new \RuntimeException('A SQL SELECT query is required for MySQL connector.');
        }
        $pdo   = $this->pdo ?? $this->buildPdo();
        $start = microtime(true);
        $stmt  = $pdo->prepare($query);
        $stmt->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows       = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'columns'     => $rows !== [] ? array_keys($rows[0]) : [],
            'rows'        => $rows,
            'duration_ms' => $durationMs,
        ];
    }

    public function getSchema(): array
    {
        $pdo     = $this->pdo ?? $this->buildPdo();
        $db      = (string) ($this->config['database'] ?? '');
        $stmt    = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = " . $pdo->quote($db) . "
             ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );
        /** @var list<array<string, mixed>> $rows */
        $rows    = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $schema  = [];
        foreach ($rows as $row) {
            $table = (string) $row['TABLE_NAME'];
            if (! isset($schema[$table])) {
                $schema[$table] = ['table' => $table, 'columns' => []];
            }
            $schema[$table]['columns'][] = [
                'name'     => $row['COLUMN_NAME'],
                'type'     => $row['DATA_TYPE'],
                'nullable' => strtoupper((string) $row['IS_NULLABLE']) === 'YES',
            ];
        }

        return array_values($schema);
    }

    private function buildPdo(): \PDO
    {
        $host     = (string) ($this->config['host'] ?? 'localhost');
        $port     = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? '');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        return new \PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 5,
            ]
        );
    }
}
