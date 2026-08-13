<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add all missing columns to Timesheets module tables:
 *  - timesheet_entries
 *  - time_allocations
 *  - project_billings (create if missing)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── timesheet_entries ─────────────────────────────────────────────────
        if (Schema::hasTable('timesheet_entries')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('timesheet_entries', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }
                if (!Schema::hasColumn('timesheet_entries', 'entry_date')) {
                    $table->date('entry_date')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'hours_worked')) {
                    $table->decimal('hours_worked', 6, 2)->default(0);
                }
                if (!Schema::hasColumn('timesheet_entries', 'billable_hours')) {
                    $table->decimal('billable_hours', 6, 2)->default(0);
                }
                if (!Schema::hasColumn('timesheet_entries', 'hourly_rate')) {
                    $table->decimal('hourly_rate', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'status')) {
                    $table->string('status')->default('draft');
                }
                if (!Schema::hasColumn('timesheet_entries', 'project_id')) {
                    $table->unsignedBigInteger('project_id')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'task_id')) {
                    $table->unsignedBigInteger('task_id')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'submitted_by')) {
                    $table->unsignedBigInteger('submitted_by')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'approval_notes')) {
                    $table->text('approval_notes')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable();
                }
                if (!Schema::hasColumn('timesheet_entries', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── time_allocations ──────────────────────────────────────────────────
        if (Schema::hasTable('time_allocations')) {
            Schema::table('time_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('time_allocations', 'timesheet_entry_id')) {
                    $table->unsignedBigInteger('timesheet_entry_id')->nullable()->index();
                }
                if (!Schema::hasColumn('time_allocations', 'entry_id')) {
                    $table->unsignedBigInteger('entry_id')->nullable()->index();
                }
                if (!Schema::hasColumn('time_allocations', 'project_id')) {
                    $table->unsignedBigInteger('project_id')->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'cost_center_id')) {
                    $table->unsignedBigInteger('cost_center_id')->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'task_id')) {
                    $table->unsignedBigInteger('task_id')->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'hours_allocated')) {
                    $table->decimal('hours_allocated', 6, 2)->default(0);
                }
                if (!Schema::hasColumn('time_allocations', 'allocation_type')) {
                    $table->string('allocation_type')->default('project');
                }
                if (!Schema::hasColumn('time_allocations', 'hourly_rate')) {
                    $table->decimal('hourly_rate', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'cost_amount')) {
                    $table->decimal('cost_amount', 14, 2)->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('time_allocations', 'billable')) {
                    $table->string('billable')->default('no');
                }
                if (!Schema::hasColumn('time_allocations', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── users — add department_id ─────────────────────────────────────────
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('department_id')->nullable();
            });
        }

        // ── time_tracking_projects ────────────────────────────────────────────
        if (Schema::hasTable('time_tracking_projects')) {
            Schema::table('time_tracking_projects', function (Blueprint $table) {
                if (!Schema::hasColumn('time_tracking_projects', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'code')) {
                    $table->string('code')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'project_id')) {
                    $table->unsignedBigInteger('project_id')->nullable()->index();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'tracking_code')) {
                    $table->string('tracking_code')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'budget_hours')) {
                    $table->decimal('budget_hours', 8, 2)->default(0);
                }
                if (!Schema::hasColumn('time_tracking_projects', 'hours_tracked')) {
                    $table->decimal('hours_tracked', 8, 2)->default(0);
                }
                if (!Schema::hasColumn('time_tracking_projects', 'hours_remaining')) {
                    $table->decimal('hours_remaining', 8, 2)->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'budget_cost')) {
                    $table->decimal('budget_cost', 14, 2)->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'status')) {
                    $table->string('status')->default('active');
                }
                if (!Schema::hasColumn('time_tracking_projects', 'start_date')) {
                    $table->timestamp('start_date')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'end_date')) {
                    $table->timestamp('end_date')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'assigned_employees')) {
                    $table->json('assigned_employees')->nullable();
                }
                if (!Schema::hasColumn('time_tracking_projects', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ── project_billings ──────────────────────────────────────────────────
        if (!Schema::hasTable('project_billings')) {
            Schema::create('project_billings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('billing_reference')->nullable();
                $table->string('invoice_reference')->nullable();
                $table->string('billing_type')->default('fixed');
                $table->decimal('amount_ht', 14, 2)->default(0);
                $table->decimal('tva_rate', 5, 2)->default(18.00);
                $table->decimal('tva_amount', 14, 2)->default(0);
                $table->decimal('amount_ttc', 14, 2)->default(0);
                $table->string('currency')->default('XOF');
                $table->string('status')->default('draft');
                $table->date('billing_date')->nullable();
                $table->date('due_date')->nullable();
                $table->decimal('milestone_percentage', 5, 2)->nullable();
                $table->decimal('hours_billed', 8, 2)->nullable();
                $table->decimal('hourly_rate', 10, 2)->nullable();
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — no rollback
    }
};
