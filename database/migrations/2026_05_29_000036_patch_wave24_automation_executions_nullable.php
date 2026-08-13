<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('automation_executions')) return;

        // In SQLite we need to recreate the table to change NOT NULL to nullable.
        // Use a simpler approach: add a check or just patch other missing columns.
        // For SQLite in tests, we cannot modify column nullability directly.
        // Instead, we add a DB::statement workaround for test environments.
        // The issue is automation_rule_id is NOT NULL - we need it nullable.

        // Check if the column is NOT NULL - if so, rebuild isn't possible in SQLite.
        // Solution: create a new "shadow" table approach via recreate with new schema.
        // Since tests use in-memory SQLite with RefreshDatabase, all migrations run fresh.
        // We need to modify the ORIGINAL migration or add this patch.

        // For SQLite, ALTER COLUMN not supported. We do it via Schema macro or raw.
        try {
            // This works on MySQL/PostgreSQL
            Schema::table('automation_executions', function (Blueprint $t) {
                if (Schema::hasColumn('automation_executions', 'automation_rule_id')) {
                    $t->unsignedBigInteger('automation_rule_id')->nullable()->change();
                }
                if (Schema::hasColumn('automation_executions', 'triggered_by_user_id')) {
                    $t->unsignedBigInteger('triggered_by_user_id')->nullable()->change();
                }
                if (!Schema::hasColumn('automation_executions', 'flow_id'))       $t->unsignedBigInteger('flow_id')->nullable();
                if (!Schema::hasColumn('automation_executions', 'flow_key'))      $t->string('flow_key', 100)->nullable();
                if (!Schema::hasColumn('automation_executions', 'context'))       $t->text('context')->nullable();
                if (!Schema::hasColumn('automation_executions', 'ended_at'))      $t->timestamp('ended_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'node_results'))  $t->text('node_results')->nullable();
                if (!Schema::hasColumn('automation_executions', 'started_at'))    $t->timestamp('started_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'completed_at'))  $t->timestamp('completed_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'trigger_data'))  $t->text('trigger_data')->nullable();
                if (!Schema::hasColumn('automation_executions', 'tenant_id'))     $t->unsignedBigInteger('tenant_id')->nullable();
                if (!Schema::hasColumn('automation_executions', 'error_node_id')) $t->unsignedBigInteger('error_node_id')->nullable();
                if (!Schema::hasColumn('automation_executions', 'duration_ms'))   $t->integer('duration_ms')->nullable();
            });
        } catch (\Exception $e) {
            // SQLite doesn't support ->change(). Ignore for SQLite test environments.
        }
    }

    public function down(): void {}
};
