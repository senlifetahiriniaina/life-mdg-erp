<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 10: `workflow_chain_executions`/`workflow_execution_steps` were
 * flagged as an active-breakage landmine in Chantier 9 round 2 (see
 * CLAUDE.md's Socle-group entry) — the catch-all scaffold migration
 * (database/migrations/2026_05_29_000003_create_all_missing_module_tables.php)
 * left both as bare id/tenant_id/status/data/timestamps stubs, but real,
 * routed code (WorkflowEngineService::runDefinition(), WorkflowExecutionController
 * ::retry(), and WorkflowChainDefinitionController::test() via
 * HrPayrollActionHandler::dispatch()) writes/reads columns that don't exist
 * on the stub schema at all — a guaranteed QueryException the first time any
 * of these paths actually runs. Column list confirmed against the real
 * model $fillable (WorkflowChainExecution, WorkflowExecutionStep) and every
 * write site (WorkflowEngineService::runDefinition(), WorkflowExecutionController
 * ::retry()), not guessed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_chain_executions', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_chain_executions', 'workflow_definition_id')) {
                $table->unsignedBigInteger('workflow_definition_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'trigger_key')) {
                $table->string('trigger_key')->nullable()->after('workflow_definition_id');
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'context_snapshot')) {
                $table->json('context_snapshot')->nullable()->after('trigger_key');
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'result_log')) {
                $table->json('result_log')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('workflow_chain_executions', 'error_message')) {
                $table->text('error_message')->nullable()->after('result_log');
            }
        });

        Schema::table('workflow_execution_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_execution_steps', 'execution_id')) {
                $table->unsignedBigInteger('execution_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'step_index')) {
                $table->unsignedInteger('step_index')->nullable()->after('execution_id');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'action_key')) {
                $table->string('action_key')->nullable()->after('step_index');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'input_context')) {
                $table->json('input_context')->nullable()->after('action_key');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'output')) {
                $table->json('output')->nullable()->after('input_context');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->nullable()->after('status');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'executed_at')) {
                $table->timestamp('executed_at')->nullable()->after('duration_ms');
            }
            if (!Schema::hasColumn('workflow_execution_steps', 'error_message')) {
                $table->text('error_message')->nullable()->after('executed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflow_chain_executions', function (Blueprint $table) {
            $table->dropColumn([
                'workflow_definition_id', 'trigger_key', 'context_snapshot',
                'started_at', 'completed_at', 'result_log', 'error_message',
            ]);
        });

        Schema::table('workflow_execution_steps', function (Blueprint $table) {
            $table->dropColumn([
                'execution_id', 'step_index', 'action_key', 'input_context',
                'output', 'duration_ms', 'executed_at', 'error_message',
            ]);
        });
    }
};
