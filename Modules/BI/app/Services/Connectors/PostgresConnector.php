<?php

declare(strict_types=1);

namespace Modules\BI\Services\Connectors;

/**
 * PostgreSQL external data source connector.
 */
class PostgresConnector implements DataSourceConnectorInterface
{
    private ?\PDO $pdo = null;

    /** @param array<string, mixed> $config */
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
            throw new \RuntimeException('A SQL SELECT query is required for PostgreSQL connector.');
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
        $pdo  = $this->pdo ?? $this->buildPdo();
        $stmt = $pdo->query(
            "SELECT table_name, column_name, data_type, is_nullable
             FROM information_schema.columns
             WHERE table_schema = 'public'
             ORDER BY table_name, ordinal_position"
        );
        /** @var list<array<string, mixed>> $rows */
        $rows   = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $schema = [];
        foreach ($rows as $row) {
            $table = (string) $row['table_name'];
            if (! isset($schema[$table])) {
                $schema[$table] = ['table' => $table, 'columns' => []];
            }
            $schema[$table]['columns'][] = [
                'name'     => $row['column_name'],
                'type'     => $row['data_type'],
                'nullable' => strtoupper((string) $row['is_nullable']) === 'YES',
            ];
        }

        return array_values($schema);
    }

    private function buildPdo(): \PDO
    {
        $host     = (string) ($this->config['host'] ?? 'localhost');
        $port     = (int) ($this->config['port'] ?? 5432);
        $database = (string) ($this->config['database'] ?? '');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        return new \PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
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
