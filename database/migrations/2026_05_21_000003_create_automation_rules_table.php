<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automation Rules Table
     *
     * Stores configurable automation rules that determine when actions
     * can be auto-executed without human approval.
     *
     * Example:
     * - Auto-receive POs under $5000 from trusted suppliers
     * - Auto-post routine journal entries (depreciation, accruals)
     * - Auto-score leads based on engagement
     */
    public function up(): void
    {
        if (!Schema::hasTable('automation_rules')) {
            Schema::create('automation_rules', function (Blueprint $table) {
                $table->id();
                $table->string('module'); // inventory, accounting, crm, etc
                $table->string('action'); // create, update, post, delete, etc
                $table->string('name')->nullable(); // Human-readable name
                $table->text('description')->nullable();
    
                // Rule conditions (JSON array of conditions)
                $table->json('conditions'); // [{ field, operator, value }, ...]
    
                // Actions to execute when conditions are met
                $table->json('actions')->nullable(); // [{ type, params }, ...]
    
                // Configuration
                $table->boolean('is_enabled')->default(true);
                $table->json('disabled_for_roles')->nullable(); // Roles that can't use this rule
    
                // Execution metadata
                $table->integer('execution_count')->default(0);
                $table->integer('skip_count')->default(0);
                $table->timestamp('last_executed_at')->nullable();
    
                // Audit
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();
    
                // Indexes
                $table->index(['module', 'action']);
                $table->index('is_enabled');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
