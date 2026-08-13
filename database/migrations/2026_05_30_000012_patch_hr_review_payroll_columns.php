<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hr_review_cycles: add status column and other missing columns
        if (Schema::hasTable('hr_review_cycles')) {
            Schema::table('hr_review_cycles', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_review_cycles', 'status')) {
                    $table->string('status')->default('draft');
                }
                if (! Schema::hasColumn('hr_review_cycles', 'cycle_id')) {
                    $table->unsignedBigInteger('cycle_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_review_cycles', 'period_start')) {
                    $table->date('period_start')->nullable();
                }
                if (! Schema::hasColumn('hr_review_cycles', 'period_end')) {
                    $table->date('period_end')->nullable();
                }
            });
        }

        // hr_performance_reviews: add cycle_id if missing
        if (Schema::hasTable('hr_performance_reviews')) {
            Schema::table('hr_performance_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_performance_reviews', 'cycle_id')) {
                    $table->unsignedBigInteger('cycle_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_performance_reviews', 'review_type')) {
                    $table->string('review_type')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_reviews', 'reviewer_id')) {
                    $table->unsignedBigInteger('reviewer_id')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_reviews', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_reviews', 'overall_rating')) {
                    $table->decimal('overall_rating', 4, 2)->nullable();
                }
            });
        }

        // hr_succession_plans: add status and reviewed_by if missing
        if (Schema::hasTable('hr_succession_plans')) {
            Schema::table('hr_succession_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_succession_plans', 'status')) {
                    $table->string('status')->default('draft');
                }
                if (! Schema::hasColumn('hr_succession_plans', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_plans', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_plans', 'fiscal_year')) {
                    $table->integer('fiscal_year')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_plans', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_plans', 'review_date')) {
                    $table->date('review_date')->nullable();
                }
            });
        }

        // hr_salary_bands: add mid_salary if missing
        if (Schema::hasTable('hr_salary_bands')) {
            Schema::table('hr_salary_bands', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_salary_bands', 'mid_salary')) {
                    $table->decimal('mid_salary', 15, 4)->nullable();
                }
            });
        }

        // hr_payroll_periods: add name if missing
        if (Schema::hasTable('hr_payroll_periods')) {
            Schema::table('hr_payroll_periods', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_payroll_periods', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('hr_payroll_periods', 'payslips')) {
                    // payslips is a relation, not a column — skip
                }
            });
        }

        // hr_payslips: add status, employee_id, payroll_period_id if missing
        if (Schema::hasTable('hr_payslips')) {
            Schema::table('hr_payslips', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_payslips', 'status')) {
                    $table->string('status')->default('draft');
                }
                if (! Schema::hasColumn('hr_payslips', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_payslips', 'payroll_period_id')) {
                    $table->unsignedBigInteger('payroll_period_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_payslips', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('hr_payslips', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable();
                }
            });
        }

        // hr_payslip_lines: add payslip_id if missing
        if (Schema::hasTable('hr_payslip_lines')) {
            Schema::table('hr_payslip_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_payslip_lines', 'payslip_id')) {
                    $table->unsignedBigInteger('payslip_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_payslip_lines', 'label')) {
                    $table->string('label')->nullable();
                }
                if (! Schema::hasColumn('hr_payslip_lines', 'amount')) {
                    $table->decimal('amount', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('hr_payslip_lines', 'type')) {
                    $table->string('type')->nullable();
                }
            });
        }
    }

    public function down(): void {}
};
