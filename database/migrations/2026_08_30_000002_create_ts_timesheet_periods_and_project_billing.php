<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.4 (Timesheets): Modules\Timesheets\Models\TimesheetPeriod
 * (weekly/bi-weekly period submission — TimesheetAdvancedController's
 * submitPeriod/approvePeriod/rejectPeriod, and the real Sheets/*.vue pages)
 * and Modules\Timesheets\Models\ProjectBilling (ProjectBillingService's
 * milestone/percentage/time-material billing) were both fully written,
 * schema-correct models with zero migration anywhere in the repo — every
 * real call fatalled with "table not found" before either the
 * try/catch-guarded ProjectBillingService's graceful demo-mode fallback,
 * or TimesheetAdvancedController's unguarded period methods, ever had real
 * data to work with.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ts_timesheet_periods')) {
            Schema::create('ts_timesheet_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_hours', 8, 2)->default(0);
                $table->decimal('billable_hours', 8, 2)->default(0);
                $table->decimal('overtime_hours', 8, 2)->default(0);
                $table->string('status')->default('draft');
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejected_reason')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'period_start']);
            });
        }

        if (! Schema::hasTable('ts_project_billing')) {
            Schema::create('ts_project_billing', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('reference')->unique();
                $table->string('billing_type')->default('fixed');
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('tva_amount', 14, 2)->default(0);
                $table->decimal('total_ttc', 14, 2)->default(0);
                $table->string('status')->default('draft');
                $table->string('ohada_account')->default('7061');
                $table->text('description')->nullable();
                $table->date('billing_date')->nullable();
                $table->unsignedBigInteger('milestone_id')->nullable();
                $table->decimal('percentage', 5, 2)->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('invoice_reference')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Non-destructive — no rollback, matching this repo's other
        // additive-only Timesheets patch migrations.
    }
};
