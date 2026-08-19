<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Captures a lightweight structural fingerprint of the database (per-table
 * column list, type, nullability, DB-level default) and diffs two such
 * snapshots — the piece that lets `backup:restore` detect when the schema
 * a backup was taken under no longer matches the current database, trace
 * exactly what changed, and apply sensible default values for data that
 * predates a since-added column.
 *
 * Deliberately structural only (columns, not indexes/constraints/triggers)
 * — the reconciliation use case only needs to know "does this row now have
 * a column it never used to, and what should live in it", not a full DDL
 * diff.
 */
class SchemaSnapshotService
{
    /**
     * @return array<string, array<string, array{type: string, nullable: bool, default: mixed}>>
     *         Keyed by table name, then column name.
     */
    public function capture(): array
    {
        $snapshot = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $columns = [];

            foreach (Schema::getColumns($name) as $column) {
                $columns[$column['name']] = [
                    'type' => $column['type_name'],
                    'nullable' => (bool) $column['nullable'],
                    'default' => $column['default'],
                ];
            }

            $snapshot[$name] = $columns;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array<string, array{type: string, nullable: bool, default: mixed}>> $old
     * @param  array<string, array<string, array{type: string, nullable: bool, default: mixed}>> $new
     * @return array{
     *     new_tables: list<string>,
     *     dropped_tables: list<string>,
     *     new_columns: array<string, array<string, array{type: string, nullable: bool, default: mixed}>>,
     *     dropped_columns: array<string, list<string>>,
     * }
     */
    public function diff(array $old, array $new): array
    {
        $newTables = array_values(array_diff(array_keys($new), array_keys($old)));
        $droppedTables = array_values(array_diff(array_keys($old), array_keys($new)));

        $newColumns = [];
        $droppedColumns = [];

        foreach ($new as $table => $columns) {
            if (! isset($old[$table])) {
                continue; // whole table is new — already covered by new_tables
            }

            $added = array_diff_key($columns, $old[$table]);
            if ($added !== []) {
                $newColumns[$table] = $added;
            }

            $removed = array_values(array_diff(array_keys($old[$table]), array_keys($columns)));
            if ($removed !== []) {
                $droppedColumns[$table] = $removed;
            }
        }

        return [
            'new_tables' => $newTables,
            'dropped_tables' => $droppedTables,
            'new_columns' => $newColumns,
            'dropped_columns' => $droppedColumns,
        ];
    }

    /**
     * A type-appropriate default for a column that exists in the current
     * schema but not in a restored backup's snapshot — used only when the
     * column has no DB-level default of its own (one that does already
     * gets backfilled onto existing rows by the ALTER TABLE itself).
     *
     * Deliberately conservative: returns null (i.e. "leave it null, don't
     * guess") for any type not confidently mappable, rather than invent a
     * plausible-looking but wrong business value.
     */
    public function defaultFor(array $columnMeta): mixed
    {
        return match (true) {
            in_array($columnMeta['type'], ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'string'], true) => '',
            in_array($columnMeta['type'], ['integer', 'bigint', 'smallint', 'tinyint', 'int'], true) => 0,
            in_array($columnMeta['type'], ['decimal', 'float', 'double', 'numeric'], true) => 0,
            $columnMeta['type'] === 'boolean' => false,
            in_array($columnMeta['type'], ['date', 'datetime', 'timestamp'], true) => now(),
            in_array($columnMeta['type'], ['json', 'jsonb'], true) => '{}',
            default => null,
        };
    }

    /**
     * Apply `defaultFor()` to every NULL value of newly-added columns that
     * have no DB-level default of their own (columns *with* a DB default
     * were already backfilled onto existing rows by the ALTER TABLE that
     * added them — nothing to do there). Returns what was actually done,
     * per table/column, for the reconciliation report.
     *
     * @param  array<string, array<string, array{type: string, nullable: bool, default: mixed}>> $newColumns
     * @return array<string, array<string, mixed>> table => column => value applied
     */
    public function backfillNewColumns(array $newColumns): array
    {
        $applied = [];

        foreach ($newColumns as $table => $columns) {
            foreach ($columns as $column => $meta) {
                if ($meta['default'] !== null) {
                    continue; // the DB already populated existing rows via its own default
                }

                $value = $this->defaultFor($meta);
                if ($value === null) {
                    continue; // no confident default to apply — leave null, report nothing
                }

                DB::table($table)->whereNull($column)->update([$column => $value]);
                $applied[$table][$column] = $value instanceof \DateTimeInterface
                    ? $value->toIso8601String()
                    : $value;
            }
        }

        return $applied;
    }
}
