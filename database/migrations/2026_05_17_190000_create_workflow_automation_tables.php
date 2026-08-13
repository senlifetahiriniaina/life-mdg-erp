<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('module'); // CRM, HR, Inventory, etc.
                $table->string('trigger_event'); // order_created, invoice_submitted, etc.
                $table->string('trigger_resource')->nullable(); // orders, invoices, etc.
                $table->json('steps'); // Workflow step definitions
                $table->json('required_roles')->nullable(); // Roles that can execute
                $table->boolean('is_enabled')->default(true)->index();
                $table->unsignedInteger('execution_count')->default(0);
                $table->timestamp('last_executed_at')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();
    
                $table->index(['tenant_id', 'module', 'is_enabled']);
                $table->index(['trigger_event', 'is_enabled']);
            });
        }

        if (!Schema::hasTable('workflow_executions')) {
            Schema::create('workflow_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
                $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('trigger_data');
                $table->enum('status', ['created', 'running', 'waiting', 'completed', 'failed']);
                $table->json('context'); // Shared data across steps
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->softDeletes();
    
                $table->index(['workflow_id', 'status']);
                $table->index(['triggered_by', 'created_at']);
            });
        }

        if (!Schema::hasTable('workflow_step_logs')) {
            Schema::create('workflow_step_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
                $table->string('step_name');
                $table->string('step_type'); // approval, condition, action
                $table->json('input_data');
                $table->json('output_data')->nullable();
                $table->enum('status', ['pending', 'completed', 'waiting', 'failed']);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
    
                $table->index(['execution_id', 'status']);
            });
        }

        if (!Schema::hasTable('approval_chains')) {
            Schema::create('approval_chains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('step_log_id')->constrained('workflow_step_logs')->cascadeOnDelete();
                $table->enum('approval_type', ['sequential', 'parallel', 'quorum']);
                $table->json('approver_sequence');
                $table->unsignedInteger('required_approvals')->default(1);
                $table->unsignedInteger('approved_count')->default(0);
                $table->unsignedInteger('rejected_count')->default(0);
                $table->unsignedSmallInteger('escalation_level')->default(0);
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
    
                $table->index(['step_log_id', 'approval_type']);
                $table->index(['completed_at']);
            });
        }

        if (!Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('chain_id')->constrained('approval_chains')->cascadeOnDelete();
                $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('escalated_to_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('sequence_order');
                $table->enum('status', ['pending', 'approved', 'rejected']);
                $table->text('comments')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->boolean('escalated')->default(false);
    
                $table->index(['chain_id', 'status']);
                $table->index(['approver_id', 'status']);
                $table->index(['due_at', 'status']);
            });
        }

        if (!Schema::hasTable('workflow_audit_logs')) {
            Schema::create('workflow_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action'); // workflow_created, step_completed, approval_requested
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->json('changes')->nullable();
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at');
    
                $table->index(['execution_id', 'action']);
                $table->index(['user_id', 'created_at']);
                $table->index(['created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_audit_logs');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_chains');
        Schema::dropIfExists('workflow_step_logs');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflows');
    }
};
