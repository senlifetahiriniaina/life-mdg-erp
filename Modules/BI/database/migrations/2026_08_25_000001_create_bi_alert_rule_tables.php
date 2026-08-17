<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.2 (BI) — AlertRule subsystem.
 *
 * `AlertRuleController` (Modules/BI/app/Http/Controllers/Api) and its
 * `AlertPolicy` are fully written and call into 7 real Eloquent models
 * (`AlertRule`, `AlertCondition`, `AlertRecipient`, `AlertEscalation`,
 * `AlertHistory`, `DndSchedule`, `AlertDeduplication`) that all declare
 * correct `$fillable`/`$casts` but never had a migration — the entire
 * subsystem was orphaned. `AlertRule::dndSchedules()`/`deduplication()`
 * and `AlertRuleController::show()`'s eager-load of `dndSchedules` mean
 * `DndSchedule`/`AlertDeduplication` are not optional extras here: without
 * their tables, `GET bi/alert-rules/{rule}` fails outright on "Base table
 * or view not found" the same way the 5 headline models would. All 7
 * tables are created together to close the gap for real, matching the
 * precedent set by the Helpdesk cs-ai migration (build every table behind
 * a live model relation, not just the ones explicitly named).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bi_alert_rules')) {
            Schema::create('bi_alert_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('metric_source');
                $table->unsignedBigInteger('metric_source_id');
                $table->string('status', 32)->default('active')->index();
                $table->boolean('is_public')->default(false);
                $table->unsignedInteger('condition_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_alert_conditions')) {
            Schema::create('bi_alert_conditions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->unsignedInteger('condition_order')->default(0);
                $table->string('operator', 32);
                $table->string('value');
                $table->string('comparison_type', 32)->default('static');
                $table->unsignedInteger('lookback_period')->nullable();
                $table->string('logic_operator', 8)->default('and');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_alert_recipients')) {
            Schema::create('bi_alert_recipients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->string('recipient_type', 32);
                $table->string('recipient_value');
                $table->string('notification_channel', 32);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_alert_escalations')) {
            Schema::create('bi_alert_escalations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->unsignedInteger('escalation_level')->default(1);
                $table->string('trigger_condition', 32);
                $table->unsignedInteger('trigger_value');
                $table->json('escalation_recipients')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_alert_history')) {
            Schema::create('bi_alert_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->string('status', 32)->default('triggered')->index();
                $table->string('severity', 32)->nullable();
                $table->decimal('triggered_value', 18, 4)->nullable();
                $table->json('condition_results')->nullable();
                $table->text('message')->nullable();
                $table->unsignedBigInteger('acknowledged_by')->nullable()->index();
                $table->timestamp('acknowledged_at')->nullable();
                $table->text('acknowledgment_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('triggered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_dnd_schedules')) {
            Schema::create('bi_dnd_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->string('recipient_value');
                $table->string('start_time', 16);
                $table->string('end_time', 16);
                $table->string('days_of_week', 32);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_alert_deduplication')) {
            Schema::create('bi_alert_deduplication', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->index();
                $table->string('grouping_key');
                $table->unsignedInteger('grouped_count')->default(0);
                $table->timestamp('first_triggered_at')->nullable();
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_alert_deduplication');
        Schema::dropIfExists('bi_dnd_schedules');
        Schema::dropIfExists('bi_alert_history');
        Schema::dropIfExists('bi_alert_escalations');
        Schema::dropIfExists('bi_alert_recipients');
        Schema::dropIfExists('bi_alert_conditions');
        Schema::dropIfExists('bi_alert_rules');
    }
};
