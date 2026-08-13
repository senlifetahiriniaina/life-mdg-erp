<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild automation_executions with all columns nullable for broad compatibility.
 * The original migration (2026_05_21_000004) has automation_rule_id as NOT NULL,
 * which conflicts with the FlowExecutionEngine that creates records without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate the table with the unified schema
        Schema::dropIfExists('automation_executions');

        if (!Schema::hasTable('automation_executions')) {
            Schema::create('automation_executions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('automation_rule_id')->nullable()->index();
                $table->unsignedBigInteger('triggered_by_user_id')->nullable();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->string('flow_key', 100)->nullable()->index();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('module', 50)->nullable();
                $table->string('trigger', 100)->nullable();
                $table->text('triggered_payload')->nullable();
                $table->text('trigger_data')->nullable();
                $table->text('context')->nullable();
                $table->string('status', 30)->default('pending');
                $table->text('error_message')->nullable();
                $table->text('execution_result')->nullable();
                $table->text('node_results')->nullable();
                $table->unsignedBigInteger('error_node_id')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->string('correlation_id', 100)->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
    }
};
