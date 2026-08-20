<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 Lot 3: `workflow_chain_definitions` was left as a bare
 * id/tenant_id/status/data/trigger_key/is_active/timestamps stub by the
 * catch-all scaffold migration — a sibling table to
 * `workflow_chain_executions`/`workflow_execution_steps`, which
 * 2026_09_05_000001_patch_workflow_chain_execution_columns.php already
 * patched, but this table itself was missed by that pass. The real,
 * routed `WorkflowChainController::storeDefinition()` (`POST
 * /api/v1/workflow-chain/definitions`) — the module's actual, primary
 * "create a workflow chain" endpoint — has never once succeeded on a real
 * call: `WorkflowChainDefinition::create()` writes `name`, `description`,
 * `trigger_module`, `conditions`, `actions`, `execution_count`,
 * `last_executed_at`, none of which existed on this table, a guaranteed
 * "no such column" SQLQueryException every time, confirmed empirically via
 * a real Pest test before this migration existed. Column list matched
 * against `WorkflowChainDefinition::$fillable`/`$casts`, not guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_chain_definitions', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_chain_definitions', 'name')) {
                $table->string('name')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'trigger_module')) {
                $table->string('trigger_module')->nullable()->after('trigger_key');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'conditions')) {
                $table->json('conditions')->nullable()->after('trigger_module');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'actions')) {
                $table->json('actions')->nullable()->after('conditions');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'execution_count')) {
                $table->unsignedInteger('execution_count')->default(0)->after('is_active');
            }
            if (!Schema::hasColumn('workflow_chain_definitions', 'last_executed_at')) {
                $table->timestamp('last_executed_at')->nullable()->after('execution_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_chain_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'description', 'trigger_module', 'conditions',
                'actions', 'execution_count', 'last_executed_at',
            ]);
        });
    }
};
