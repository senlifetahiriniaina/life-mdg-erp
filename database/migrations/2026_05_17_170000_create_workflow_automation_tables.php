<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Workflows - Define workflow processes
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('trigger_event', 100); // order_created, task_assigned, etc.
                $table->json('steps'); // Workflow step definitions
                $table->boolean('is_enabled')->default(true)->index();
                $table->unsignedInteger('execution_count')->default(0);
                $table->timestamp('last_executed_at')->nullable();
                $table->unsignedBigInteger('created_by')->index();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
                $table->index(['trigger_event', 'is_enabled']);
            });
        }

        // Workflow Executions - Track workflow runs
        if (!Schema::hasTable('workflow_executions')) {
            Schema::create('workflow_executions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workflow_id')->index();
                $table->unsignedBigInteger('triggered_by')->nullable(); // User who triggered
                $table->json('trigger_data'); // Initial trigger data
                $table->string('status', 50); // running, completed, failed
                $table->json('context'); // Shared context across steps
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);
                $table->text('error_message')->nullable();

                $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
                $table->foreign('triggered_by')->references('id')->on('users')->onDelete('set null');
                $table->index(['workflow_id', 'status']);
                $table->index('started_at');
            });
        }

        // Workflow Step Logs - Track each step execution
        if (!Schema::hasTable('workflow_step_logs')) {
            Schema::create('workflow_step_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('execution_id')->index();
                $table->string('step_name', 255);
                $table->string('step_type', 50); // approval, action, condition, etc.
                $table->json('input_data')->nullable();
                $table->json('output_data')->nullable();
                $table->string('status', 50); // pending, completed, failed, waiting
                $table->text('error_message')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('duration_ms')->default(0);

                $table->foreign('execution_id')->references('id')->on('workflow_executions')->onDelete('cascade');
                $table->index(['execution_id', 'step_name']);
            });
        }

        // Approval Chains - Sequential approvers for approval steps
        if (!Schema::hasTable('approval_chains')) {
            Schema::create('approval_chains', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('step_log_id')->unique()->index();
                $table->string('approval_type', 50); // sequential, parallel, quorum
                $table->json('approver_sequence'); // [{order: 1, user_id: 1, status: pending}, ...]
                $table->integer('required_approvals')->nullable(); // For quorum/parallel
                $table->integer('approved_count')->default(0);
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('escalated_at')->nullable();

                $table->foreign('step_log_id')->references('id')->on('workflow_step_logs')->onDelete('cascade');
                $table->index('started_at');
            });
        }

        // Approvals - Individual approval records
        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('chain_id')->index();
                $table->unsignedBigInteger('approver_id')->index();
                $table->unsignedInteger('sequence_order');
                $table->string('status', 50); // pending, approved, rejected
                $table->text('comments')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->boolean('escalated')->default(false);

                $table->foreign('chain_id')->references('id')->on('approval_chains')->onDelete('cascade');
                $table->foreign('approver_id')->references('id')->on('users')->onDelete('restrict');
                $table->index(['chain_id', 'status']);
                $table->index('due_at');
            });
        }

        // Workflow Conditions - Track condition evaluation
        if (!Schema::hasTable('workflow_conditions')) {
            Schema::create('workflow_conditions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('step_log_id')->unique()->index();
                $table->json('condition_expression'); // {type: AND, rules: [...]}
                $table->json('evaluated_values'); // Values extracted from context
                $table->boolean('result')->nullable(); // true/false/null (not evaluated)
                $table->string('next_step_true', 255)->nullable(); // Step name if true
                $table->string('next_step_false', 255)->nullable(); // Step name if false
                $table->timestamp('evaluated_at')->nullable();

                $table->foreign('step_log_id')->references('id')->on('workflow_step_logs')->onDelete('cascade');
            });
        }

        // Workflow Audit Log - Complete audit trail
        if (!Schema::hasTable('workflow_audit_logs')) {
            Schema::create('workflow_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('execution_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 50); // step_started, step_completed, approval_given, etc.
                $table->string('entity_type', 50)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('changes')->nullable(); // What changed
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at');

                $table->foreign('execution_id')->references('id')->on('workflow_executions')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->index(['execution_id', 'action']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_audit_logs');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_chains');
        Schema::dropIfExists('workflow_step_logs');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflows');
    }
};
