<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hr_appraisals_v2: add overall_score, strengths, improvements, goals_next, completed_at
        if (Schema::hasTable('hr_appraisals_v2')) {
            Schema::table('hr_appraisals_v2', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_appraisals_v2', 'overall_score')) {
                    $table->decimal('overall_score', 4, 1)->nullable();
                }
                if (! Schema::hasColumn('hr_appraisals_v2', 'strengths')) {
                    $table->text('strengths')->nullable();
                }
                if (! Schema::hasColumn('hr_appraisals_v2', 'improvements')) {
                    $table->text('improvements')->nullable();
                }
                if (! Schema::hasColumn('hr_appraisals_v2', 'goals_next')) {
                    $table->text('goals_next')->nullable();
                }
                if (! Schema::hasColumn('hr_appraisals_v2', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
            });
        }

        // hr_succession_plans_v2: add incumbent_employee_id
        if (Schema::hasTable('hr_succession_plans_v2')) {
            Schema::table('hr_succession_plans_v2', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_succession_plans_v2', 'incumbent_employee_id')) {
                    $table->unsignedBigInteger('incumbent_employee_id')->nullable()->index();
                }
            });
        }

        // hr_succession_candidates_v2: create if missing, or patch columns
        if (! Schema::hasTable('hr_succession_candidates_v2')) {
            Schema::create('hr_succession_candidates_v2', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->string('readiness')->nullable();
                $table->string('potential')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('hr_succession_candidates_v2', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_succession_candidates_v2', 'plan_id')) {
                    $table->unsignedBigInteger('plan_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_succession_candidates_v2', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_succession_candidates_v2', 'readiness')) {
                    $table->string('readiness')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates_v2', 'potential')) {
                    $table->string('potential')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates_v2', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        // hr_performance_goals: create if missing, or patch columns
        if (! Schema::hasTable('hr_performance_goals')) {
            Schema::create('hr_performance_goals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appraisal_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->date('due_date')->nullable();
                $table->decimal('progress', 5, 2)->nullable();
                $table->decimal('weight', 5, 2)->nullable();
                $table->decimal('score', 5, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('hr_performance_goals', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_performance_goals', 'appraisal_id')) {
                    $table->unsignedBigInteger('appraisal_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'due_date')) {
                    $table->date('due_date')->nullable();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'progress')) {
                    $table->decimal('progress', 5, 2)->nullable();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'weight')) {
                    $table->decimal('weight', 5, 2)->nullable();
                }
                if (! Schema::hasColumn('hr_performance_goals', 'score')) {
                    $table->decimal('score', 5, 2)->nullable();
                }
            });
        }

        // hr_critical_positions: patch columns from stub (id, tenant_id, status, data, timestamps)
        if (Schema::hasTable('hr_critical_positions')) {
            Schema::table('hr_critical_positions', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_critical_positions', 'succession_plan_id')) {
                    $table->unsignedBigInteger('succession_plan_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'department')) {
                    $table->string('department')->nullable();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'risk_level')) {
                    $table->string('risk_level')->nullable();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'current_holder_id')) {
                    $table->unsignedBigInteger('current_holder_id')->nullable();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'impact_description')) {
                    $table->text('impact_description')->nullable();
                }
                if (! Schema::hasColumn('hr_critical_positions', 'is_vacant')) {
                    $table->boolean('is_vacant')->default(false);
                }
            });
        }

        // hr_succession_candidates: patch columns
        if (Schema::hasTable('hr_succession_candidates')) {
            Schema::table('hr_succession_candidates', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_succession_candidates', 'critical_position_id')) {
                    $table->unsignedBigInteger('critical_position_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'employee_id')) {
                    $table->unsignedBigInteger('employee_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'readiness')) {
                    $table->string('readiness')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'priority')) {
                    $table->integer('priority')->default(1);
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'strengths')) {
                    $table->text('strengths')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'development_needs')) {
                    $table->text('development_needs')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'development_plan')) {
                    $table->text('development_plan')->nullable();
                }
                if (! Schema::hasColumn('hr_succession_candidates', 'last_reviewed_at')) {
                    $table->timestamp('last_reviewed_at')->nullable();
                }
            });
        }

        // hr_leave_requests: add days_requested if missing
        if (Schema::hasTable('hr_leave_requests')) {
            Schema::table('hr_leave_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_leave_requests', 'days_requested')) {
                    $table->integer('days_requested')->nullable();
                }
            });
        }
    }

    public function down(): void {}
};
