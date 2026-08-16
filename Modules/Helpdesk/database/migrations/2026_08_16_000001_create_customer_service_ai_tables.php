<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\Helpdesk\Http\Controllers\Api\CustomerServiceAIController` (sentiment
 * analysis, escalation prediction, AI response suggestions, satisfaction/NPS
 * prediction, agent performance analytics) references 17 real Eloquent models
 * under `Modules\Helpdesk\Models\` — each with a real, correct
 * $fillable/$casts/$table — but none of their `cs_*` tables were ever created
 * by any migration. This migration creates those 17 tables plus 3 auxiliary
 * models (SentimentModel, SentimentHistory, AIResponseVariant) that the
 * controller actually eager-loads via `->with(...)` (SatisfactionModel,
 * EscalationHistory, and ResponsePerformance are NOT eager-loaded anywhere in
 * this controller, so their tables are intentionally not created here).
 */
return new class extends Migration {
    public function up(): void
    {
        // ─── Sentiment analysis ───

        if (! Schema::hasTable('cs_sentiment_models')) {
            Schema::create('cs_sentiment_models', function (Blueprint $table) {
                $table->id();
                $table->string('name', 128);
                $table->string('language', 16)->nullable();
                $table->string('model_type', 64)->nullable();
                $table->string('provider', 64)->nullable();
                $table->string('model_identifier', 128)->nullable();
                $table->decimal('accuracy', 8, 4)->nullable();
                $table->unsignedInteger('training_samples')->nullable();
                $table->timestamp('trained_at')->nullable();
                $table->timestamp('deployed_at')->nullable();
                $table->string('status', 32)->nullable()->index();
                $table->json('hyperparameters')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cs_sentiment_scores')) {
            Schema::create('cs_sentiment_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('sentiment_model_id')->nullable()->constrained('cs_sentiment_models')->nullOnDelete();
                $table->string('language_detected', 16)->nullable();
                $table->string('sentiment', 32)->nullable()->index();
                $table->decimal('positive_score', 8, 4)->nullable();
                $table->decimal('negative_score', 8, 4)->nullable();
                $table->decimal('neutral_score', 8, 4)->nullable();
                $table->decimal('confidence', 8, 4)->nullable();
                $table->text('analyzed_text')->nullable();
                $table->json('tokens')->nullable();
                $table->string('status', 32)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_sentiment_history')) {
            Schema::create('cs_sentiment_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('sentiment_score_id')->nullable()->constrained('cs_sentiment_scores')->cascadeOnDelete();
                $table->string('sentiment_trend', 32)->nullable();
                $table->decimal('sentiment_change', 8, 4)->nullable();
                $table->decimal('positive_score', 8, 4)->nullable();
                $table->decimal('negative_score', 8, 4)->nullable();
                $table->decimal('neutral_score', 8, 4)->nullable();
                $table->string('trigger_event', 64)->nullable();
                $table->unsignedInteger('message_count')->nullable();
                $table->json('metrics')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_emotion_analysis')) {
            Schema::create('cs_emotion_analysis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('sentiment_score_id')->nullable()->constrained('cs_sentiment_scores')->nullOnDelete();
                $table->decimal('anger_score', 8, 4)->nullable();
                $table->decimal('frustration_score', 8, 4)->nullable();
                $table->decimal('satisfaction_score', 8, 4)->nullable();
                $table->decimal('confusion_score', 8, 4)->nullable();
                $table->decimal('urgency_score', 8, 4)->nullable();
                $table->decimal('disappointment_score', 8, 4)->nullable();
                $table->string('dominant_emotion', 32)->nullable();
                $table->string('emotional_state', 32)->nullable();
                $table->integer('emotional_intensity')->nullable();
                $table->boolean('sentiment_shift_detected')->default(false);
                $table->json('emotion_sequence')->nullable();
                $table->text('context_notes')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_language_detection')) {
            Schema::create('cs_language_detection', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('detected_language', 16)->nullable();
                $table->decimal('confidence', 8, 4)->nullable();
                $table->string('original_text_language', 16)->nullable();
                $table->string('supported_language', 16)->nullable();
                $table->boolean('requires_translation')->default(false);
                $table->string('translation_provider', 64)->nullable();
                $table->text('translated_text')->nullable();
                $table->string('translation_status', 32)->nullable();
                $table->decimal('translation_confidence', 8, 4)->nullable();
                $table->json('language_alternatives')->nullable();
                $table->text('detection_notes')->nullable();
                $table->timestamp('detected_at')->nullable();
                $table->timestamp('translated_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Routing ───

        if (! Schema::hasTable('cs_routing_rules')) {
            Schema::create('cs_routing_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->string('name', 128);
                $table->string('rule_type', 32);
                $table->string('sentiment_trigger', 16)->nullable();
                $table->string('target_queue', 32)->nullable();
                $table->integer('priority_boost')->nullable();
                $table->boolean('requires_specialist')->default(false);
                $table->string('skill_required', 64)->nullable();
                $table->json('routing_conditions')->nullable();
                $table->string('escalation_path', 64)->nullable();
                $table->integer('max_wait_minutes')->nullable();
                $table->integer('sla_hours_override')->nullable();
                $table->boolean('notify_customer')->default(false);
                $table->text('notification_message')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->integer('order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ─── Escalation prediction ───

        if (! Schema::hasTable('cs_escalation_models')) {
            Schema::create('cs_escalation_models', function (Blueprint $table) {
                $table->id();
                $table->string('name', 128);
                $table->string('model_type', 64)->nullable();
                $table->string('provider', 64)->nullable();
                $table->string('model_identifier', 128)->nullable();
                $table->decimal('precision', 8, 4)->nullable();
                $table->decimal('recall', 8, 4)->nullable();
                $table->decimal('f1_score', 8, 4)->nullable();
                $table->unsignedInteger('training_samples')->nullable();
                $table->timestamp('trained_at')->nullable();
                $table->timestamp('deployed_at')->nullable();
                $table->string('status', 32)->nullable()->index();
                $table->json('feature_importance')->nullable();
                $table->json('hyperparameters')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cs_escalation_predictions')) {
            Schema::create('cs_escalation_predictions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('escalation_model_id')->nullable()->constrained('cs_escalation_models')->nullOnDelete();
                $table->decimal('urgency_score', 8, 4)->nullable();
                $table->decimal('escalation_probability', 8, 4)->nullable();
                $table->decimal('confidence', 8, 4)->nullable();
                $table->string('recommended_action', 64)->nullable();
                $table->string('escalation_level', 32)->nullable();
                $table->integer('estimated_resolution_hours')->nullable();
                $table->json('contributing_factors')->nullable();
                $table->string('status', 32)->nullable();
                $table->boolean('escalated')->default(false);
                $table->timestamp('escalated_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_urgency_factors')) {
            Schema::create('cs_urgency_factors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->decimal('wait_time_hours', 8, 2)->nullable();
                $table->decimal('sentiment_factor', 8, 4)->nullable();
                $table->decimal('issue_complexity_factor', 8, 4)->nullable();
                $table->decimal('agent_skill_factor', 8, 4)->nullable();
                $table->decimal('customer_vip_factor', 8, 4)->nullable();
                $table->decimal('sla_breach_factor', 8, 4)->nullable();
                $table->decimal('repeat_issue_factor', 8, 4)->nullable();
                $table->decimal('channel_factor', 8, 4)->nullable();
                $table->decimal('business_hours_factor', 8, 4)->nullable();
                $table->decimal('concurrent_escalations_factor', 8, 4)->nullable();
                $table->decimal('total_urgency_score', 8, 4)->nullable();
                $table->json('factor_breakdown')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── AI response suggestions ───

        if (! Schema::hasTable('cs_response_templates')) {
            Schema::create('cs_response_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('title', 200);
                $table->text('content')->nullable();
                $table->string('category', 64)->nullable()->index();
                $table->string('language', 16)->nullable()->index();
                $table->json('tags')->nullable();
                $table->string('use_case', 128)->nullable();
                $table->json('variables')->nullable();
                $table->string('tone', 32)->nullable()->index();
                $table->integer('avg_resolution_time_minutes')->nullable();
                $table->decimal('avg_satisfaction_rating', 6, 2)->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->unsignedInteger('positive_feedback_count')->default(0);
                $table->unsignedInteger('negative_feedback_count')->default(0);
                $table->string('status', 16)->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cs_ai_response_variants')) {
            Schema::create('cs_ai_response_variants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->nullable()->constrained('cs_response_templates')->cascadeOnDelete();
                $table->string('variant_type', 32)->nullable();
                $table->text('content')->nullable();
                $table->string('target_sentiment', 16)->nullable();
                $table->string('target_emotion', 32)->nullable();
                $table->string('target_context', 64)->nullable();
                $table->decimal('relevance_score', 8, 4)->nullable();
                $table->json('triggers')->nullable();
                $table->decimal('avg_satisfaction_rating', 6, 2)->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->unsignedInteger('positive_feedback_count')->default(0);
                $table->unsignedInteger('negative_feedback_count')->default(0);
                $table->boolean('ai_generated')->default(false);
                $table->string('generation_model', 64)->nullable();
                $table->string('status', 16)->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_response_suggestions')) {
            Schema::create('cs_response_suggestions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('template_id')->nullable()->constrained('cs_response_templates')->nullOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('cs_ai_response_variants')->nullOnDelete();
                $table->text('suggested_response')->nullable();
                $table->text('suggestion_reason')->nullable();
                $table->decimal('relevance_score', 8, 4)->nullable();
                $table->decimal('confidence', 8, 4)->nullable();
                $table->json('matching_factors')->nullable();
                $table->boolean('used')->default(false)->index();
                $table->boolean('accepted')->nullable();
                $table->text('modified_response')->nullable();
                $table->boolean('modification_significant')->nullable();
                $table->decimal('feedback_rating', 4, 2)->nullable();
                $table->string('feedback_type', 32)->nullable();
                $table->text('feedback_notes')->nullable();
                $table->timestamp('suggested_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Satisfaction / NPS prediction ───

        if (! Schema::hasTable('cs_satisfaction_predictions')) {
            Schema::create('cs_satisfaction_predictions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                // No FK: SatisfactionModel's table is never eager-loaded by
                // CustomerServiceAIController, so it is intentionally not created here.
                $table->unsignedBigInteger('satisfaction_model_id')->nullable();
                $table->integer('predicted_satisfaction_score')->nullable();
                $table->decimal('confidence', 8, 4)->nullable();
                $table->string('satisfaction_category', 32)->nullable();
                $table->json('contributing_factors')->nullable();
                $table->json('risk_factors')->nullable();
                $table->json('improvement_suggestions')->nullable();
                $table->string('status', 32)->nullable();
                $table->boolean('prediction_correct')->nullable();
                $table->integer('actual_satisfaction_score')->nullable();
                $table->integer('prediction_error')->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_nps_predictors')) {
            Schema::create('cs_nps_predictors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->decimal('predicted_nps_score', 6, 2)->nullable();
                $table->string('promoter_likelihood', 32)->nullable();
                $table->decimal('promoter_probability', 8, 4)->nullable();
                $table->decimal('passive_probability', 8, 4)->nullable();
                $table->decimal('detractor_probability', 8, 4)->nullable();
                $table->json('nps_influencing_factors')->nullable();
                $table->string('recommendation_likelihood', 32)->nullable();
                $table->json('recommendation_sentiment')->nullable();
                $table->decimal('advocacy_score', 8, 4)->nullable();
                $table->boolean('is_repeat_customer')->default(false);
                $table->integer('lifetime_value_segment')->nullable();
                $table->string('customer_segment', 32)->nullable();
                $table->json('churn_risk_indicators')->nullable();
                $table->decimal('churn_probability', 8, 4)->nullable();
                $table->integer('actual_nps_score')->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamps();
            });
        }

        // ─── Agent performance analytics ───

        if (! Schema::hasTable('cs_agent_metrics')) {
            Schema::create('cs_agent_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->date('metric_date')->nullable()->index();
                $table->integer('tickets_handled')->nullable();
                $table->integer('tickets_resolved')->nullable();
                $table->integer('first_contact_resolution_count')->nullable();
                $table->decimal('first_contact_resolution_rate', 8, 4)->nullable();
                $table->integer('avg_resolution_time_minutes')->nullable();
                $table->integer('avg_response_time_seconds')->nullable();
                $table->integer('avg_handle_time_seconds')->nullable();
                $table->decimal('avg_satisfaction_rating', 6, 2)->nullable();
                $table->integer('satisfaction_survey_responses')->nullable();
                $table->decimal('avg_sentiment_improvement', 8, 4)->nullable();
                $table->integer('escalation_count')->nullable();
                $table->decimal('escalation_rate', 8, 4)->nullable();
                $table->integer('repeat_contact_count')->nullable();
                $table->decimal('repeat_contact_rate', 8, 4)->nullable();
                $table->integer('quality_audit_score')->nullable();
                $table->integer('nps_detractor_count')->nullable();
                $table->integer('nps_passive_count')->nullable();
                $table->integer('nps_promoter_count')->nullable();
                $table->decimal('nps_score', 6, 2)->nullable();
                $table->decimal('productivity_score', 8, 4)->nullable();
                $table->decimal('quality_score', 8, 4)->nullable();
                $table->decimal('overall_performance_score', 8, 4)->nullable();
                $table->string('performance_rating', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_agent_performance_trends')) {
            Schema::create('cs_agent_performance_trends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->string('period_type', 16)->nullable()->index();
                $table->date('period_start_date')->nullable();
                $table->date('period_end_date')->nullable();
                $table->decimal('satisfaction_trend', 8, 4)->nullable();
                $table->decimal('resolution_time_trend', 8, 4)->nullable();
                $table->decimal('productivity_trend', 8, 4)->nullable();
                $table->decimal('quality_trend', 8, 4)->nullable();
                $table->decimal('escalation_trend', 8, 4)->nullable();
                $table->json('trend_summary')->nullable();
                $table->string('performance_direction', 16)->nullable();
                $table->integer('improvement_points')->nullable();
                $table->integer('decline_points')->nullable();
                $table->json('top_improvements')->nullable();
                $table->json('areas_needing_improvement')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_agent_skill_analysis')) {
            Schema::create('cs_agent_skill_analysis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->string('skill_category', 64)->nullable()->index();
                $table->string('skill_name', 128)->nullable();
                $table->string('skill_level', 16)->nullable();
                $table->integer('proficiency_score')->nullable();
                $table->integer('tickets_handled_for_skill')->nullable();
                $table->decimal('avg_satisfaction_for_skill', 6, 2)->nullable();
                $table->decimal('first_contact_resolution_rate_for_skill', 8, 4)->nullable();
                $table->integer('avg_resolution_time_for_skill_minutes')->nullable();
                $table->integer('escalation_count_for_skill')->nullable();
                $table->decimal('escalation_rate_for_skill', 8, 4)->nullable();
                $table->integer('skill_improvement_points')->nullable();
                $table->timestamp('skill_certified_at')->nullable();
                $table->timestamp('skill_last_practiced_at')->nullable();
                $table->string('proficiency_trend', 16)->nullable();
                $table->integer('days_since_practice')->nullable();
                $table->boolean('needs_training')->default(false);
                $table->json('training_recommendations')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_agent_coaching_recommendations')) {
            Schema::create('cs_agent_coaching_recommendations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->unsignedBigInteger('generated_by')->nullable()->index();
                $table->string('recommendation_type', 64)->nullable();
                $table->string('priority', 16)->nullable()->index();
                $table->text('description')->nullable();
                $table->string('target_metric', 64)->nullable();
                $table->integer('current_performance')->nullable();
                $table->integer('target_performance')->nullable();
                $table->json('recommended_actions')->nullable();
                $table->string('training_program', 128)->nullable();
                $table->json('resources')->nullable();
                $table->date('target_completion_date')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('expected_improvement', 8, 4)->nullable();
                $table->decimal('actual_improvement', 8, 4)->nullable();
                $table->text('feedback_notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cs_team_benchmarking')) {
            Schema::create('cs_team_benchmarking', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id')->nullable()->index();
                $table->date('benchmark_date')->nullable()->index();
                $table->integer('team_size')->nullable();
                $table->decimal('avg_satisfaction_rating', 6, 2)->nullable();
                $table->decimal('median_satisfaction_rating', 6, 2)->nullable();
                $table->decimal('top_performer_satisfaction', 6, 2)->nullable();
                $table->decimal('bottom_performer_satisfaction', 6, 2)->nullable();
                $table->integer('avg_resolution_time_minutes')->nullable();
                $table->integer('best_resolution_time_minutes')->nullable();
                $table->integer('worst_resolution_time_minutes')->nullable();
                $table->decimal('avg_escalation_rate', 8, 4)->nullable();
                $table->decimal('avg_first_contact_resolution_rate', 8, 4)->nullable();
                $table->decimal('avg_nps_score', 6, 2)->nullable();
                $table->decimal('avg_quality_score', 8, 4)->nullable();
                $table->decimal('avg_productivity_score', 8, 4)->nullable();
                $table->integer('top_performer_rank')->nullable();
                $table->integer('bottom_performer_rank')->nullable();
                $table->json('performance_distribution')->nullable();
                $table->json('strengths')->nullable();
                $table->json('improvement_areas')->nullable();
                $table->decimal('team_trend', 8, 4)->nullable();
                $table->string('team_performance_rating', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_performance_goals')) {
            Schema::create('cs_performance_goals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('goal_type', 32)->nullable();
                $table->text('goal_description')->nullable();
                $table->string('goal_category', 64)->nullable()->index();
                $table->string('metric_name', 64)->nullable();
                $table->integer('baseline_value')->nullable();
                $table->integer('target_value')->nullable();
                $table->string('measurement_unit', 32)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('frequency', 16)->nullable();
                $table->integer('weight')->nullable();
                $table->decimal('current_progress', 8, 4)->nullable();
                $table->string('status', 16)->default('active')->index();
                $table->string('progress_status', 16)->nullable();
                $table->json('milestone_dates')->nullable();
                $table->json('achievements')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('achieved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_performance_goals');
        Schema::dropIfExists('cs_team_benchmarking');
        Schema::dropIfExists('cs_agent_coaching_recommendations');
        Schema::dropIfExists('cs_agent_skill_analysis');
        Schema::dropIfExists('cs_agent_performance_trends');
        Schema::dropIfExists('cs_agent_metrics');
        Schema::dropIfExists('cs_nps_predictors');
        Schema::dropIfExists('cs_satisfaction_predictions');
        Schema::dropIfExists('cs_response_suggestions');
        Schema::dropIfExists('cs_ai_response_variants');
        Schema::dropIfExists('cs_response_templates');
        Schema::dropIfExists('cs_urgency_factors');
        Schema::dropIfExists('cs_escalation_predictions');
        Schema::dropIfExists('cs_escalation_models');
        Schema::dropIfExists('cs_routing_rules');
        Schema::dropIfExists('cs_language_detection');
        Schema::dropIfExists('cs_emotion_analysis');
        Schema::dropIfExists('cs_sentiment_history');
        Schema::dropIfExists('cs_sentiment_scores');
        Schema::dropIfExists('cs_sentiment_models');
    }
};
