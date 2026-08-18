<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.4: BudgetLine/ProjectRisk are real, fully-written models
 * (relations, OHADA account mapping, EVM accessors) already used by live,
 * routed ProjectAdvancedController::budget()/kpis()/risks() endpoints via
 * ProjectBudgetService — but prj_budget_lines/prj_risks had no migration
 * at all, so every one of those endpoints fatals with "table not found"
 * on first real touch. prj_expense_ledger (append-only actual-expense log,
 * no Eloquent model — written via raw DB::table() from
 * ProjectBudgetService::recordActualExpense(), already try/catch-guarded
 * there for exactly this "table may not exist yet" case) is added too so
 * that graceful degradation becomes real functionality instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prj_budget_lines')) {
            Schema::create('prj_budget_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
                $table->string('description');
                $table->string('category')->default('opex'); // capex | opex
                $table->string('ohada_account')->nullable();
                $table->decimal('estimated_amount', 15, 2)->default(0);
                $table->decimal('actual_amount', 15, 2)->default(0);
                $table->string('phase')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('project_id');
            });
        }

        if (! Schema::hasTable('prj_expense_ledger')) {
            Schema::create('prj_expense_ledger', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_line_id')->constrained('prj_budget_lines')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('reference')->nullable();
                $table->dateTime('recorded_at')->nullable();
                $table->timestamps();

                $table->index('budget_line_id');
            });
        }

        if (! Schema::hasTable('prj_risks')) {
            Schema::create('prj_risks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('prj_projects')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('open'); // open | in_review | mitigated | closed
                $table->string('probability')->default('medium'); // low | medium | high
                $table->string('impact')->default('medium'); // low | medium | high
                $table->unsignedTinyInteger('probability_score')->nullable();
                $table->unsignedTinyInteger('impact_score')->nullable();
                $table->text('mitigation_plan')->nullable();
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('due_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('project_id');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prj_risks');
        Schema::dropIfExists('prj_expense_ledger');
        Schema::dropIfExists('prj_budget_lines');
    }
};
