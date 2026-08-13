<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── wfd_definitions ───────────────────────────────────────────────
        $this->patch('wfd_definitions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_conditions'))  $t->text('trigger_conditions')->nullable();
            if (!Schema::hasColumn($table, 'description'))         $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'module'))              $t->string('module', 50)->nullable();
            if (!Schema::hasColumn($table, 'trigger_event'))       $t->string('trigger_event', 100)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))           $t->boolean('is_active')->default(true);
            if (!Schema::hasColumn($table, 'created_by'))          $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))          $t->softDeletes();
            if (!Schema::hasColumn($table, 'last_run_at'))         $t->timestamp('last_run_at')->nullable();
            if (!Schema::hasColumn($table, 'run_count'))           $t->integer('run_count')->default(0);
        });

        // ── wfd_actions ───────────────────────────────────────────────────
        $this->patch('wfd_actions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))         $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'order'))               $t->integer('order')->default(0);
            if (!Schema::hasColumn($table, 'action_type'))         $t->string('action_type', 50)->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))          $t->softDeletes();
        });

        // ── wfd_executions ────────────────────────────────────────────────
        $this->patch('wfd_executions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workflow_id'))         $t->unsignedBigInteger('workflow_id')->nullable();
            if (!Schema::hasColumn($table, 'deleted_at'))          $t->softDeletes();
        });

        // ── wfd_execution_logs ────────────────────────────────────────────
        $this->patch('wfd_execution_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'execution_id'))        $t->unsignedBigInteger('execution_id')->nullable();
            if (!Schema::hasColumn($table, 'action_id'))           $t->unsignedBigInteger('action_id')->nullable();
            if (!Schema::hasColumn($table, 'status'))              $t->string('status', 20)->default('success');
            if (!Schema::hasColumn($table, 'output'))              $t->text('output')->nullable();
            if (!Schema::hasColumn($table, 'error_message'))       $t->text('error_message')->nullable();
            if (!Schema::hasColumn($table, 'executed_at'))         $t->timestamp('executed_at')->nullable();
            if (!Schema::hasColumn($table, 'duration_ms'))         $t->integer('duration_ms')->nullable();
        });
    }

    public function down(): void {}
};
