<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) return;
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // Add softDeletes to planning swap/coverage request tables
        $this->patch('planning_shift_swap_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) $t->softDeletes();
        });

        $this->patch('planning_shift_coverage_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) $t->softDeletes();
        });

        // Create canonical Planning tables (models were renamed to match test expectations)
        if (!Schema::hasTable('planning_coverage_requests')) {
            Schema::create('planning_coverage_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->unsignedBigInteger('schedule_id')->nullable();
                $t->unsignedBigInteger('requested_by')->nullable();
                $t->text('reason')->nullable();
                $t->text('description')->nullable();
                $t->string('status')->default('open');
                $t->unsignedBigInteger('assigned_to')->nullable();
                $t->timestamp('assigned_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (!Schema::hasTable('planning_swap_requests')) {
            Schema::create('planning_swap_requests', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->unsignedBigInteger('requester_id')->nullable();
                $t->unsignedBigInteger('requested_schedule_id')->nullable();
                $t->unsignedBigInteger('target_employee_id')->nullable();
                $t->unsignedBigInteger('target_schedule_id')->nullable();
                $t->text('reason')->nullable();
                $t->string('status')->default('pending');
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->text('approval_notes')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (!Schema::hasTable('planning_schedules')) {
            Schema::create('planning_schedules', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->unsignedBigInteger('shift_id')->nullable();
                $t->date('scheduled_date')->nullable();
                $t->string('status')->nullable();
                $t->text('notes')->nullable();
                $t->unsignedBigInteger('assigned_by')->nullable();
                $t->timestamp('assigned_at')->nullable();
                $t->unsignedBigInteger('confirmed_by')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        // Accounting: acc_journal_entries missing date & source_type columns
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'date'))        $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'source_type')) $t->string('source_type')->nullable();
            if (!Schema::hasColumn($table, 'source_id'))   $t->unsignedBigInteger('source_id')->nullable();
        });

        // Accounting: acc_budget_actuals missing columns
        $this->patch('acc_budget_actuals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'budget_id'))      $t->unsignedBigInteger('budget_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'budget_line_id')) $t->unsignedBigInteger('budget_line_id')->nullable()->index();
        });

        // Create planning_conflicts table (used by ScheduleConflict model in tests)
        if (!Schema::hasTable('planning_conflicts')) {
            Schema::create('planning_conflicts', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->unsignedBigInteger('employee_id')->nullable();
                $t->date('conflict_date')->nullable();
                $t->string('conflict_type')->nullable();
                $t->text('description')->nullable();
                $t->string('severity')->nullable();
                $t->string('status')->default('unresolved');
                $t->text('resolution_notes')->nullable();
                $t->unsignedBigInteger('resolved_by')->nullable();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void {}
};
