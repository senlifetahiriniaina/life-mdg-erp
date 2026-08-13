<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requester_id')->constrained('users');
                $table->string('module'); // accounting, crm, inventory, etc
                $table->string('action'); // create, update, delete, post, etc
                $table->json('payload'); // The data being submitted
                $table->json('impact_report')->nullable(); // Impact analysis results
                $table->enum('status', [
                    'pending',
                    'approved',
                    'rejected',
                    'rolled_back',
                    'expired',
                    'cancelled',
                ])->default('pending');
    
                // Approval workflow metadata
                $table->string('approval_type')->default('sequential'); // sequential, parallel, quorum
                $table->integer('required_approvals')->default(1);
                $table->text('rejection_reason')->nullable();
                $table->text('rollback_reason')->nullable();
    
                // Timestamps
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->timestamp('expires_at')->nullable();
    
                // Indexes for querying
                $table->index(['requester_id', 'status']);
                $table->index(['module', 'action']);
                $table->index(['status']);
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
