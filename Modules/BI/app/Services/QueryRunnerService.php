<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\BI\Models\BiQuery;

class QueryRunnerService
{
    /** SQL keywords that are forbidden in read-only queries */
    private const FORBIDDEN_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'EXEC',
    ];

    /**
     * Validate that SQL is SELECT-only (no write/DDL keywords).
     *
     * @throws \InvalidArgumentException
     */
    public function validateSelectOnly(string $sql): void
    {
        $normalised = preg_replace('/\s+/', ' ', strtoupper(trim($sql))) ?? '';

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            // Match as standalone word (not inside identifiers)
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $normalised)) {
                throw new \InvalidArgumentException("SQL contains forbidden keyword: {$keyword}");
            }
        }

        // Must start with SELECT or WITH (CTEs)
        if (! preg_match('/^\s*(SELECT|WITH)\b/i', $sql)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }
    }

    /**
     * Run a saved BiQuery (with caching).
     *
     * @param  array<string, mixed>  $params
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    public function runQuery(BiQuery $query, array $params = []): array
    {
        $cacheKey = 'bi_query_'.$query->id.'_'.md5(serialize($params));

        if ($query->result_cache_ttl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                /** @var array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int} $cached */
                return $cached;
            }
        }

        $result = $this->executeQuery($query->sql_query, $params);

        if ($query->result_cache_ttl > 0) {
            $this->cacheQuery($query, $result);
        }

        $query->updateQuietly(['last_run_at' => now()]);

        return $result;
    }

    /**
     * Run an ad-hoc SQL string (no model, strict validation).
     *
     * @param  array<string, mixed>  $params
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    public function runRawQuery(string $sql, array $params = []): array
    {
        $this->validateSelectOnly($sql);

        return $this->executeQuery($sql, $params);
    }

    /**
     * Store query result in cache.
     *
     * @param  array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}  $result
     */
    public function cacheQuery(BiQuery $query, array $result): void
    {
        $cacheKey = 'bi_query_'.$query->id.'_'.md5('[]');
        Cache::put($cacheKey, $result, $query->result_cache_ttl);
    }

    /**
     * Convert a query result to CSV string.
     *
     * @param  array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}  $result
     */
    public function exportCsv(array $result): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        fputcsv($output, $result['columns']);
        foreach ($result['rows'] as $row) {
            fputcsv($output, array_values($row));
        }

        rewind($output);
        $csv = stream_get_contents($output) ?: '';
        fclose($output);

        return $csv;
    }

    /**
     * Execute a validated SQL query against the read-only connection.
     *
     * @param  array<string, mixed>  $params
     * @return array{columns: list<string>, rows: list<array<string, mixed>>, duration_ms: int}
     */
    private function executeQuery(string $sql, array $params = []): array
    {
        $this->validateSelectOnly($sql);

        $connection = config('database.default') === 'sqlite' ? 'sqlite' : 'readonly';

        $start = microtime(true);
        /** @var list<\stdClass> $rawRows */
        $rawRows = DB::connection($connection)->select($sql, $params);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $rows = array_map(fn (\stdClass $r): array => (array) $r, $rawRows);
        $columns = $rows !== [] ? array_keys($rows[0]) : [];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'duration_ms' => $durationMs,
        ];
    }
}
