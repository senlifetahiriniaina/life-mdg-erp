<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_08_16_000001_create_customer_service_ai_tables.php` created 17 of the
 * 25 cs-ai tables, explicitly excluding SatisfactionModel/EscalationHistory/
 * ResponsePerformance because none were eager-loaded by the controller at the
 * time. Chantier 8.2's full audit of Modules/Helpdesk found 5 more models in
 * the same situation (ConversationAnalytics, EscalationWorkflow,
 * ResponseCustomization, SatisfactionFactor, SatisfactionHistory) — real
 * models with correct $fillable/$casts, referenced only via relations
 * (EscalationPrediction::history(), ResponseTemplate::performance(),
 * SatisfactionPrediction::history(), etc.) that nothing currently calls, so
 * the missing tables are dormant rather than actively crashing. Still a real
 * landmine: the first controller/service that eager-loads one of these
 * relations gets a "Base table or view not found" SQL error instead of a
 * clean empty collection. Creates all 8 tables now, matching every other
 * model-with-no-table gap already fixed elsewhere in this codebase.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('hd_conversation_analytics')) {
            Schema::create('hd_conversation_analytics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('channel', 32)->nullable();
                $table->unsignedInteger('message_count')->default(0);
                $table->unsignedInteger('response_count')->default(0);
                $table->unsignedInteger('first_response_time_seconds')->nullable();
                $table->unsignedInteger('avg_response_time_seconds')->nullable();
                $table->unsignedInteger('resolution_time_seconds')->nullable();
                $table->decimal('sentiment_score', 8, 4)->nullable();
                $table->decimal('delivery_rate', 8, 4)->default(0);
                $table->decimal('read_rate', 8, 4)->default(0);
                $table->decimal('click_rate', 8, 4)->default(0);
                $table->unsignedInteger('escalations_count')->default(0);
                $table->unsignedInteger('transfers_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_escalation_workflows')) {
            Schema::create('cs_escalation_workflows', function (Blueprint $table) {
                $table->id();
                $table->string('name', 128);
                $table->text('description')->nullable();
                $table->string('escalation_level', 32)->nullable()->index();
                $table->unsignedInteger('min_urgency_score')->default(0);
                $table->boolean('approval_required')->default(false);
                $table->json('approval_roles')->nullable();
                $table->unsignedInteger('escalation_time_minutes')->nullable();
                $table->string('next_level', 32)->nullable();
                $table->boolean('send_notifications')->default(true);
                $table->json('notification_recipients')->nullable();
                $table->string('notification_template', 128)->nullable();
                $table->json('reassignment_rules')->nullable();
                $table->string('priority_level', 32)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('cs_response_customization')) {
            Schema::create('cs_response_customization', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->nullable()->index();
                $table->foreignId('template_id')->nullable()->constrained('cs_response_templates')->nullOnDelete();
                $table->string('preferred_variation', 64)->nullable();
                $table->string('tone_preference', 32)->nullable();
                $table->json('frequent_modifications')->nullable();
                $table->decimal('customization_score', 8, 4)->default(0);
                $table->unsignedInteger('times_used')->default(0);
                $table->unsignedInteger('times_modified')->default(0);
                $table->decimal('avg_satisfaction_with_variant', 5, 2)->nullable();
                $table->boolean('has_custom_variant')->default(false);
                $table->foreignId('custom_variant_id')->nullable()->constrained('cs_ai_response_variants')->nullOnDelete();
                $table->json('learning_data')->nullable();
                $table->boolean('is_learning_enabled')->default(true);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_response_performance')) {
            Schema::create('cs_response_performance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->nullable()->constrained('cs_response_templates')->nullOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('cs_ai_response_variants')->nullOnDelete();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('response_type', 32)->nullable();
                $table->decimal('satisfaction_rating', 5, 2)->nullable();
                $table->string('feedback_category', 64)->nullable();
                $table->unsignedInteger('response_time_seconds')->nullable();
                $table->unsignedInteger('ticket_resolution_time_minutes')->nullable();
                $table->boolean('issue_resolved')->default(false);
                $table->decimal('resolution_effectiveness', 8, 4)->nullable();
                $table->unsignedInteger('follow_up_count')->default(0);
                $table->boolean('required_escalation')->default(false);
                $table->boolean('required_additional_response')->default(false);
                $table->string('customer_sentiment_after', 32)->nullable();
                $table->json('metrics')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_satisfaction_factors')) {
            Schema::create('cs_satisfaction_factors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->unsignedInteger('resolution_time_minutes')->nullable();
                $table->decimal('resolution_time_factor', 8, 4)->default(0);
                $table->boolean('first_contact_resolution')->default(false);
                $table->string('agent_professionalism', 32)->nullable();
                $table->decimal('agent_professionalism_factor', 8, 4)->default(0);
                $table->string('agent_friendliness', 32)->nullable();
                $table->decimal('agent_friendliness_factor', 8, 4)->default(0);
                $table->string('agent_knowledge_level', 32)->nullable();
                $table->decimal('agent_knowledge_factor', 8, 4)->default(0);
                $table->string('communication_quality', 32)->nullable();
                $table->decimal('communication_factor', 8, 4)->default(0);
                $table->string('problem_understanding', 32)->nullable();
                $table->decimal('problem_understanding_factor', 8, 4)->default(0);
                $table->string('solution_effectiveness', 32)->nullable();
                $table->decimal('solution_effectiveness_factor', 8, 4)->default(0);
                $table->boolean('customer_expectation_met')->default(false);
                $table->decimal('expectation_factor', 8, 4)->default(0);
                $table->unsignedInteger('follow_up_quality_rating')->nullable();
                $table->decimal('follow_up_factor', 8, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_satisfaction_models')) {
            Schema::create('cs_satisfaction_models', function (Blueprint $table) {
                $table->id();
                $table->string('name', 128);
                $table->string('model_type', 64)->nullable();
                $table->string('provider', 64)->nullable();
                $table->string('model_identifier', 128)->nullable();
                $table->decimal('rmse', 8, 4)->nullable();
                $table->decimal('mae', 8, 4)->nullable();
                $table->decimal('r_squared', 8, 4)->nullable();
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

        if (! Schema::hasTable('cs_escalation_history')) {
            Schema::create('cs_escalation_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('escalation_prediction_id')->nullable()->constrained('cs_escalation_predictions')->nullOnDelete();
                $table->decimal('predicted_urgency', 8, 4)->nullable();
                $table->decimal('predicted_probability', 8, 4)->nullable();
                $table->boolean('prediction_correct')->nullable();
                $table->decimal('actual_escalation_probability', 8, 4)->nullable();
                $table->string('actual_action', 64)->nullable();
                $table->boolean('was_escalated')->default(false);
                $table->unsignedInteger('escalation_delay_minutes')->nullable();
                $table->decimal('model_accuracy_impact', 8, 4)->nullable();
                $table->json('feedback')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cs_satisfaction_history')) {
            Schema::create('cs_satisfaction_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->foreignId('satisfaction_prediction_id')->nullable()->constrained('cs_satisfaction_predictions')->nullOnDelete();
                $table->unsignedInteger('predicted_score')->nullable();
                $table->unsignedInteger('actual_score')->nullable();
                $table->integer('prediction_error')->nullable();
                $table->boolean('prediction_accurate')->nullable();
                $table->string('satisfaction_category', 64)->nullable();
                $table->json('improvement_actions')->nullable();
                $table->boolean('score_improved')->nullable();
                $table->integer('score_improvement_points')->nullable();
                $table->json('model_feedback')->nullable();
                $table->decimal('model_learning_impact', 8, 4)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_satisfaction_history');
        Schema::dropIfExists('cs_escalation_history');
        Schema::dropIfExists('cs_satisfaction_models');
        Schema::dropIfExists('cs_satisfaction_factors');
        Schema::dropIfExists('cs_response_performance');
        Schema::dropIfExists('cs_response_customization');
        Schema::dropIfExists('cs_escalation_workflows');
        Schema::dropIfExists('hd_conversation_analytics');
    }
};
