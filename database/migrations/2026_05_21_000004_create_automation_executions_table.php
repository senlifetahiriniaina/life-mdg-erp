<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automation Executions Table
     *
     * Audit trail for all automatic executions.
     * Required for compliance and debugging.
     */
    public function up(): void
    {
        if (!Schema::hasTable('automation_executions')) {
            Schema::create('automation_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('automation_rule_id')->constrained('automation_rules');
                $table->foreignId('triggered_by_user_id')->nullable()->constrained('users');
    
                $table->string('module');
                $table->string('trigger');
                $table->json('triggered_payload'); // Data that triggered the rule
    
                $table->enum('status', [
                    'executed',
                    'failed',
                    'skipped',
                    'cancelled',
                ])->default('executed');
    
                $table->text('error_message')->nullable();
                $table->json('execution_result')->nullable();
    
                // For traceability
                $table->string('correlation_id')->nullable(); // To track related actions
                $table->timestamps();
    
                // Indexes
                $table->index(['automation_rule_id', 'status']);
                $table->index('status');
                $table->index('correlation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_executions');
    }
};
