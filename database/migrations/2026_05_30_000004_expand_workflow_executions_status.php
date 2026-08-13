<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expand workflow_executions.status from a strict enum to a plain string,
 * allowing values like 'success' in addition to the original set.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_executions')) {
            return;
        }

        Schema::table('workflow_executions', function (Blueprint $table) {
            // Change enum to string so 'success', 'completed', etc. are all valid
            $table->string('status')->default('created')->change();

            // Add extra columns if not yet present (from WorkflowAutomation module)
            if (!Schema::hasColumn('workflow_executions', 'trigger_entity_id')) {
                $table->unsignedBigInteger('trigger_entity_id')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'trigger_entity_type')) {
                $table->string('trigger_entity_type')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'triggered_at')) {
                $table->timestamp('triggered_at')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'execution_depth')) {
                $table->unsignedInteger('execution_depth')->default(0);
            }
            if (!Schema::hasColumn('workflow_executions', 'payload_size_bytes')) {
                $table->unsignedInteger('payload_size_bytes')->default(0);
            }
            if (!Schema::hasColumn('workflow_executions', 'parent_execution_id')) {
                $table->unsignedBigInteger('parent_execution_id')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'trigger_data')) {
                $table->json('trigger_data')->nullable();
            }
            if (!Schema::hasColumn('workflow_executions', 'context')) {
                $table->json('context')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive — no rollback
    }
};
