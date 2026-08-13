<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_engagement_surveys')) {
            Schema::create('hr_engagement_surveys', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->enum('type', ['pulse', 'annual', 'enps'])->default('pulse');
                $table->enum('status', ['draft', 'active', 'closed', 'archived'])->default('draft');
                $table->text('description')->nullable();
                $table->json('schedule')->nullable();          // { frequency, start_date, end_date }
                $table->json('target_audience')->nullable();   // { type: all|department|team|role, ids: [], count: n }
                $table->boolean('is_anonymous')->default(true);
                $table->timestamp('distributed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('type');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('hr_survey_questions')) {
            Schema::create('hr_survey_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')
                    ->constrained('hr_engagement_surveys')
                    ->cascadeOnDelete();
                $table->enum('type', ['rating', 'scale', 'text', 'choice'])->default('rating');
                $table->string('question_fr');
                $table->string('question_en');
                $table->json('options')->nullable();   // for choice/scale: [{label, value}, ...]
                $table->decimal('weight', 4, 3)->default(1.000);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('required')->default(true);
                $table->timestamps();

                $table->index('survey_id');
            });
        }

        if (! Schema::hasTable('hr_survey_responses')) {
            Schema::create('hr_survey_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')
                    ->constrained('hr_engagement_surveys')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id')->nullable(); // null in full-anon mode
                $table->json('answers');                                // { question_id: value, ... }
                $table->boolean('is_anonymous')->default(true);
                $table->string('department_id')->nullable();           // kept for segmentation
                $table->string('team_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index('survey_id');
                $table->index('employee_id');
                // Prevent double submission per employee per survey
                $table->unique(['survey_id', 'employee_id'], 'uq_survey_employee_response');
            });
        }

        if (! Schema::hasTable('hr_survey_results')) {
            Schema::create('hr_survey_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_id')
                    ->constrained('hr_engagement_surveys')
                    ->cascadeOnDelete();
                // eNPS specific
                $table->decimal('enps_score', 6, 2)->nullable();
                $table->unsignedInteger('promoters_count')->nullable();
                $table->unsignedInteger('passives_count')->nullable();
                $table->unsignedInteger('detractors_count')->nullable();
                // Pulse / general
                $table->json('question_averages')->nullable();    // { question_id: avg }
                $table->json('department_breakdown')->nullable(); // { dept_id: { avg, count } }
                $table->json('team_breakdown')->nullable();
                $table->unsignedInteger('total_responses')->default(0);
                $table->decimal('response_rate', 5, 2)->default(0);
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->unique('survey_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_survey_results');
        Schema::dropIfExists('hr_survey_responses');
        Schema::dropIfExists('hr_survey_questions');
        Schema::dropIfExists('hr_engagement_surveys');
    }
};
