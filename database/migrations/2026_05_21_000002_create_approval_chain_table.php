<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approval Chain Table
     *
     * Each approval request has multiple approval_chain rows (one per approver)
     * This allows tracking of sequential/parallel approvals and escalations
     */
    public function up(): void
    {
        if (!Schema::hasTable('approval_chain')) {
            Schema::create('approval_chain', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_request_id')->constrained('approval_requests')->onDelete('cascade');
                $table->foreignId('approver_id')->constrained('users');
                $table->enum('status', [
                    'pending',
                    'approved',
                    'rejected',
                    'escalated',
                    'reassigned',
                    'expired',
                ])->default('pending');
    
                // Approval details
                $table->text('comment')->nullable();
                $table->integer('escalation_level')->default(0);
    
                // Sequential order (for sequential approval workflows)
                $table->integer('sequence_order')->nullable();
    
                // Timestamps
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('reminded_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
    
                // Indexes
                $table->index(['approval_request_id', 'status']);
                $table->index(['approver_id', 'status']);
                $table->index('due_at');
                $table->unique(['approval_request_id', 'approver_id', 'escalation_level'], 'appr_chain_req_approver_level_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chain');
    }
};
