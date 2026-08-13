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
        // Add role column to users table (needed by many HR tests)
        $this->patch('users', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'role')) $t->string('role')->default('user');
        });

        $this->patch('hr_appraisals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'self_rating'))          $t->decimal('self_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'manager_rating'))       $t->decimal('manager_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'self_assessment'))      $t->text('self_assessment')->nullable();
            if (!Schema::hasColumn($table, 'manager_comments'))     $t->text('manager_comments')->nullable();
            if (!Schema::hasColumn($table, 'strengths'))            $t->text('strengths')->nullable();
            if (!Schema::hasColumn($table, 'areas_for_improvement'))$t->text('areas_for_improvement')->nullable();
            if (!Schema::hasColumn($table, 'development_plan'))     $t->text('development_plan')->nullable();
            if (!Schema::hasColumn($table, 'submitted_at'))         $t->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn($table, 'reviewed_at'))          $t->timestamp('reviewed_at')->nullable();
            if (!Schema::hasColumn($table, 'acknowledged_at'))      $t->timestamp('acknowledged_at')->nullable();
        });

        $this->patch('hr_attendance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))     $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'check_in'))  $t->string('check_in')->nullable();
            if (!Schema::hasColumn($table, 'check_out')) $t->string('check_out')->nullable();
        });

        $this->patch('hr_candidates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_updated_at')) $t->timestamp('last_updated_at')->nullable();
            if (!Schema::hasColumn($table, 'notes'))           $t->text('notes')->nullable();
        });

        $this->patch('hr_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'format'))    $t->string('format')->nullable();
            if (!Schema::hasColumn($table, 'mandatory')) $t->boolean('mandatory')->default(false);
        });

        $this->patch('hr_critical_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'department_id')) $t->unsignedBigInteger('department_id')->nullable();
        });

        $this->patch('hr_appraisal_competencies', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'appraisal_id'))    $t->unsignedBigInteger('appraisal_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'name'))            $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))     $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'self_rating'))     $t->decimal('self_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'manager_rating'))  $t->decimal('manager_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'comments'))        $t->text('comments')->nullable();
        });

        $this->patch('hr_appraisal_goals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'appraisal_id'))  $t->unsignedBigInteger('appraisal_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'title'))         $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'category'))      $t->string('category')->nullable();
            if (!Schema::hasColumn($table, 'target_value'))  $t->decimal('target_value', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'actual_value'))  $t->decimal('actual_value', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'weight'))        $t->integer('weight')->default(1);
            if (!Schema::hasColumn($table, 'status'))        $t->string('status')->default('in_progress');
            if (!Schema::hasColumn($table, 'due_date'))      $t->date('due_date')->nullable();
            if (!Schema::hasColumn($table, 'self_rating'))   $t->decimal('self_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'manager_rating'))$t->decimal('manager_rating', 4, 2)->nullable();
        });

        $this->patch('hr_course_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'course_id'))    $t->unsignedBigInteger('course_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'progress_pct')) $t->integer('progress_pct')->default(0);
            if (!Schema::hasColumn($table, 'started_at'))   $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'completed_at')) $t->timestamp('completed_at')->nullable();
        });

        $this->patch('hr_interviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'scheduled_at'))     $t->timestamp('scheduled_at')->nullable();
            if (!Schema::hasColumn($table, 'type'))             $t->string('type')->nullable();
            if (!Schema::hasColumn($table, 'candidate_id'))     $t->unsignedBigInteger('candidate_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'duration_minutes')) $t->integer('duration_minutes')->nullable();
            if (!Schema::hasColumn($table, 'completed_at'))     $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'feedback'))         $t->text('feedback')->nullable();
            if (!Schema::hasColumn($table, 'rating'))           $t->decimal('rating', 4, 2)->nullable();
        });

        $this->patch('hr_job_applications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'job_posting_id'))    $t->unsignedBigInteger('job_posting_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'applicant_name'))    $t->string('applicant_name')->nullable();
            if (!Schema::hasColumn($table, 'applicant_email'))   $t->string('applicant_email')->nullable();
            if (!Schema::hasColumn($table, 'applicant_phone'))   $t->string('applicant_phone')->nullable();
            if (!Schema::hasColumn($table, 'source'))            $t->string('source')->nullable();
        });

        $this->patch('hr_job_postings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'applications_count'))  $t->unsignedInteger('applications_count')->default(0);
            if (!Schema::hasColumn($table, 'positions_available')) $t->unsignedInteger('positions_available')->default(1);
            if (!Schema::hasColumn($table, 'published_at'))        $t->timestamp('published_at')->nullable();
        });

        $this->patch('hr_leave_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approval_notes')) $t->text('approval_notes')->nullable();
        });

        $this->patch('hr_performance_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'overall_rating'))         $t->decimal('overall_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'strengths'))              $t->text('strengths')->nullable();
            if (!Schema::hasColumn($table, 'areas_for_improvement'))  $t->text('areas_for_improvement')->nullable();
            if (!Schema::hasColumn($table, 'goals_next_period'))      $t->text('goals_next_period')->nullable();
            if (!Schema::hasColumn($table, 'comments'))               $t->text('comments')->nullable();
            if (!Schema::hasColumn($table, 'submitted_at'))           $t->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))            $t->timestamp('approved_at')->nullable();
        });

        $this->patch('hr_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'department_id')) $t->unsignedBigInteger('department_id')->nullable()->index();
        });

        $this->patch('hr_recruitment_applicants', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'job_id'))          $t->unsignedBigInteger('job_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'first_name'))      $t->string('first_name')->nullable();
            if (!Schema::hasColumn($table, 'last_name'))       $t->string('last_name')->nullable();
            if (!Schema::hasColumn($table, 'email'))           $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'phone'))           $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'resume_url'))      $t->string('resume_url')->nullable();
            if (!Schema::hasColumn($table, 'cover_letter'))    $t->text('cover_letter')->nullable();
            if (!Schema::hasColumn($table, 'application_date'))$t->date('application_date')->nullable();
            if (!Schema::hasColumn($table, 'rating'))          $t->decimal('rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'interview_notes')) $t->text('interview_notes')->nullable();
            if (!Schema::hasColumn($table, 'interview_date'))  $t->timestamp('interview_date')->nullable();
        });

        $this->patch('hr_recruitment_jobs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'job_posting_date')) $t->date('job_posting_date')->nullable();
            if (!Schema::hasColumn($table, 'requirements'))     $t->text('requirements')->nullable();
            if (!Schema::hasColumn($table, 'salary_min'))       $t->decimal('salary_min', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'salary_max'))       $t->decimal('salary_max', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'type'))             $t->string('type')->nullable();
        });

        $this->patch('hr_review_goals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'review_id'))      $t->unsignedBigInteger('review_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'title'))          $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'description'))    $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'target_value'))   $t->decimal('target_value', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'achieved_value')) $t->decimal('achieved_value', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'weight'))         $t->integer('weight')->default(20);
            if (!Schema::hasColumn($table, 'status'))         $t->string('status')->default('pending');
            if (!Schema::hasColumn($table, 'due_date'))       $t->date('due_date')->nullable();
        });

        $this->patch('hr_succession_candidates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'critical_position_id')) $t->unsignedBigInteger('critical_position_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'employee_id'))          $t->unsignedBigInteger('employee_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'readiness'))            $t->string('readiness')->nullable();
            if (!Schema::hasColumn($table, 'priority'))             $t->integer('priority')->default(1);
            if (!Schema::hasColumn($table, 'strengths'))            $t->text('strengths')->nullable();
            if (!Schema::hasColumn($table, 'development_needs'))    $t->text('development_needs')->nullable();
            if (!Schema::hasColumn($table, 'development_plan'))     $t->text('development_plan')->nullable();
            if (!Schema::hasColumn($table, 'last_reviewed_at'))     $t->timestamp('last_reviewed_at')->nullable();
        });

        $this->patch('hr_training_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'format'))    $t->string('format')->nullable();
            if (!Schema::hasColumn($table, 'mandatory')) $t->boolean('mandatory')->default(false);
        });

        $this->patch('hr_training_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'course_id'))    $t->unsignedBigInteger('course_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'completed_at')) $t->timestamp('completed_at')->nullable();
        });

        $this->patch('hr_payslip_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'label')) {
                $t->string('label')->nullable();
            } else {
                $t->string('label')->nullable()->change();
            }
        });

        if (!Schema::hasTable('hr_appraisals_v2')) {
            Schema::create('hr_appraisals_v2', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->unsignedBigInteger('cycle_id')->nullable()->index();
                $t->unsignedBigInteger('employee_id')->nullable()->index();
                $t->unsignedBigInteger('reviewer_id')->nullable();
                $t->string('status')->default('pending');
                $t->decimal('overall_rating', 4, 2)->nullable();
                $t->text('comments')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (!Schema::hasTable('hr_succession_plans_v2')) {
            Schema::create('hr_succession_plans_v2', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->string('position')->nullable();
                $t->string('department')->nullable();
                $t->string('criticality')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void {}
};
