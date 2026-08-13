<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── automation_flows ──────────────────────────────────────────────
        if (!Schema::hasTable('automation_flows')) {
            Schema::create('automation_flows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('key', 100)->nullable()->index();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('trigger', 100)->nullable();
                $table->string('trigger_type', 50)->nullable();
                $table->text('trigger_config')->nullable();
                $table->text('nodes')->nullable();
                $table->text('edges')->nullable();
                $table->string('status', 20)->default('draft');
                $table->boolean('is_active')->default(true);
                $table->string('icon', 50)->nullable();
                $table->string('color', 20)->nullable();
                $table->text('tags')->nullable();
                $table->integer('version')->default(1);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->integer('total_runs')->default(0);
                $table->integer('success_runs')->default(0);
                $table->timestamps();
            });
        }

        // ── automation_nodes ──────────────────────────────────────────────
        if (!Schema::hasTable('automation_nodes')) {
            Schema::create('automation_nodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->string('node_id', 100)->nullable();
                $table->string('type', 50)->nullable();
                $table->text('config')->nullable();
                $table->integer('position_x')->default(0);
                $table->integer('position_y')->default(0);
                $table->text('on_success')->nullable();
                $table->text('on_error')->nullable();
                $table->text('data')->nullable();
                $table->timestamps();
            });
        }

        // ── automation_connections ────────────────────────────────────────
        if (!Schema::hasTable('automation_connections')) {
            Schema::create('automation_connections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->string('source_node_id', 100)->nullable();
                $table->string('target_node_id', 100)->nullable();
                $table->string('condition', 200)->nullable();
                $table->text('data')->nullable();
                $table->timestamps();
            });
        }

        // ── automation_variables ──────────────────────────────────────────
        if (!Schema::hasTable('automation_variables')) {
            Schema::create('automation_variables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->string('name', 100)->nullable();
                $table->string('type', 30)->default('string');
                $table->text('default_value')->nullable();
                $table->timestamps();
            });
        }

        // ── automation_flow_templates ─────────────────────────────────────
        if (!Schema::hasTable('automation_flow_templates')) {
            Schema::create('automation_flow_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->nullable()->unique();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('category', 50)->nullable();
                $table->text('nodes')->nullable();
                $table->text('edges')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('usage_count')->default(0);
                $table->timestamps();
            });
        }

        // ── automation_executions — patch missing columns ──────────────────
        if (!Schema::hasTable('automation_executions')) {
            Schema::create('automation_executions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flow_id')->nullable()->index();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('flow_key', 100)->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('trigger_data')->nullable();
                $table->text('context')->nullable();
                $table->text('node_results')->nullable();
                $table->string('error_node_id', 100)->nullable();
                $table->integer('duration_ms')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        } else {
            // Add missing columns to existing table
            Schema::table('automation_executions', function (Blueprint $t) {
                if (!Schema::hasColumn('automation_executions', 'flow_key'))    $t->string('flow_key', 100)->nullable();
                if (!Schema::hasColumn('automation_executions', 'context'))     $t->text('context')->nullable();
                if (!Schema::hasColumn('automation_executions', 'ended_at'))    $t->timestamp('ended_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'flow_id'))     $t->unsignedBigInteger('flow_id')->nullable();
                if (!Schema::hasColumn('automation_executions', 'tenant_id'))   $t->unsignedBigInteger('tenant_id')->nullable();
                if (!Schema::hasColumn('automation_executions', 'started_at'))  $t->timestamp('started_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'completed_at'))$t->timestamp('completed_at')->nullable();
                if (!Schema::hasColumn('automation_executions', 'node_results'))$t->text('node_results')->nullable();
                if (!Schema::hasColumn('automation_executions', 'trigger_data'))$t->text('trigger_data')->nullable();
            });
        }
    }

    public function down(): void {}
};
