<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.11 — layer 14 (performance): FlowExecutionEngine::execute()'s
 * DFS graph traversal (traverse() → resolveNextNodes()) runs one
 * `AutomationConnection::query()->where('source_node_id', $node->id)->get()`
 * per node visited, up to MAX_NODES_PER_RUN (200) times per single flow
 * execution — a real, confirmed N+1 across the flow graph, not a
 * hypothetical one. `automation_connections` had no index at all on
 * `source_node_id`/`target_node_id` (confirmed via the table's original
 * creation migration, 2026_05_29_000034_patch_wave22_automation_flows_tables.php),
 * meaning every one of those per-node queries was a full table scan. Rather
 * than refactor the traversal engine to preload the whole flow's connection
 * graph once (a wider, riskier change to a safety-critical recursive
 * execution path — loop/retry/sub-flow error handling all recurse through
 * the same method — deliberately left as a documented follow-up, not
 * attempted under this chantier's surgical scope), this closes the cheap,
 * safe, purely-additive half of the fix: an index turns each of those N
 * per-node queries from a full scan into an indexed lookup, which is where
 * almost all of the real latency at scale actually comes from for a small
 * per-flow node count (typically tens, not thousands, per
 * MAX_NODES_PER_RUN's own 200-node safety ceiling).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_connections')) {
            return;
        }

        Schema::table('automation_connections', function (Blueprint $table) {
            if (! $this->indexExists('automation_connections', 'automation_connections_source_node_id_index')) {
                $table->index('source_node_id');
            }
            if (! $this->indexExists('automation_connections', 'automation_connections_target_node_id_index')) {
                $table->index('target_node_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('automation_connections')) {
            return;
        }

        Schema::table('automation_connections', function (Blueprint $table) {
            $table->dropIndex('automation_connections_source_node_id_index');
            $table->dropIndex('automation_connections_target_node_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        // MySQL / other drivers
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }
        return false;
    }
};
