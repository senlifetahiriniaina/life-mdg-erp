<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Strategy module never had any migrations at all — 18 models/factories/
 * services/tests were built against tables that were never created. Columns
 * derived from each model's $fillable/$casts/docblocks (the factories were
 * bulk-generated with generic fields that don't match the real models and
 * are not used here). Created in dependency order so FK-shaped columns can
 * reference an already-created parent table; none of these are enforced FK
 * constraints (cross-module + self-referencing columns), matching this
 * migration's additive/non-destructive convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('strategy_plans')) {
            Schema::create('strategy_plans', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('vision')->nullable();
                $table->text('mission')->nullable();
                $table->integer('period_start')->nullable();
                $table->integer('period_end')->nullable();
                $table->string('framework')->nullable();
                $table->string('status')->default('active');
                $table->integer('health_score')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_pillars')) {
            Schema::create('strategy_pillars', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color')->nullable();
                $table->string('icon')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_kpis')) {
            Schema::create('strategy_kpis', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->string('source_module')->nullable();
                $table->string('source_key')->nullable();
                $table->string('source_aggregation')->nullable();
                $table->json('source_filter')->nullable();
                $table->string('unit')->nullable();
                $table->string('frequency')->nullable();
                $table->decimal('target_value', 18, 4)->nullable();
                $table->decimal('warning_threshold', 18, 4)->nullable();
                $table->decimal('critical_threshold', 18, 4)->nullable();
                $table->boolean('higher_is_better')->default(true);
                $table->boolean('is_public')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_kpi_values')) {
            Schema::create('strategy_kpi_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kpi_id')->index();
                $table->decimal('value', 18, 4);
                $table->dateTime('recorded_at')->nullable()->index();
                $table->string('period')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_ratios')) {
            Schema::create('strategy_ratios', function (Blueprint $table) {
                $table->id();
                $table->string('module')->nullable()->index();
                $table->string('name');
                $table->unsignedBigInteger('numerator_kpi_id')->nullable()->index();
                $table->unsignedBigInteger('denominator_kpi_id')->nullable()->index();
                $table->string('formula')->nullable();
                $table->string('benchmark_category')->nullable();
                $table->text('description')->nullable();
                $table->string('unit')->nullable();
                $table->string('direction')->nullable();
                $table->decimal('target_min', 18, 4)->nullable();
                $table->decimal('target_max', 18, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_ratio_snapshots')) {
            Schema::create('strategy_ratio_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ratio_id')->index();
                $table->string('tenant_id')->nullable()->index();
                $table->string('period');
                $table->decimal('value', 18, 4)->nullable();
                $table->decimal('benchmark_value', 18, 4)->nullable();
                $table->decimal('gap', 18, 4)->nullable();
                // Append-only snapshot table: model sets `public $timestamps = false`
                // and fills created_at manually — no updated_at column.
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('strategy_objectives')) {
            Schema::create('strategy_objectives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->unsignedBigInteger('pillar_id')->nullable()->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('level')->nullable();
                $table->string('owner_type')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('framework_type')->nullable();
                $table->string('bsc_perspective')->nullable();
                $table->decimal('weight', 5, 2)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('active');
                $table->decimal('progress', 5, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_key_results')) {
            Schema::create('strategy_key_results', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('objective_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('type')->nullable();
                $table->decimal('baseline_value', 18, 4)->nullable();
                $table->decimal('target_value', 18, 4)->nullable();
                $table->decimal('current_value', 18, 4)->nullable();
                $table->string('unit')->nullable();
                $table->string('data_source_module')->nullable();
                $table->string('data_source_key')->nullable();
                $table->decimal('progress', 5, 2)->default(0);
                $table->decimal('confidence', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_objective_links')) {
            Schema::create('strategy_objective_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('strategy_objective_id')->index();
                $table->string('linkable_type');
                $table->unsignedBigInteger('linkable_id');
                $table->decimal('contribution_value', 18, 4)->nullable();
                $table->string('unit_type')->nullable();
                $table->timestamps();

                $table->unique(
                    ['strategy_objective_id', 'linkable_type', 'linkable_id'],
                    'strategy_objective_links_unique'
                );
            });
        }

        if (! Schema::hasTable('strategy_kros')) {
            Schema::create('strategy_kros', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('objective_id')->index();
                $table->unsignedBigInteger('kpi_id')->nullable()->index();
                $table->decimal('target', 18, 4)->nullable();
                $table->decimal('baseline', 18, 4)->nullable();
                $table->decimal('current', 18, 4)->nullable();
                $table->decimal('weight', 5, 2)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_correlations')) {
            Schema::create('strategy_correlations', function (Blueprint $table) {
                $table->id();
                $table->string('kpi_a');
                $table->string('kpi_b');
                $table->decimal('coefficient', 6, 4)->nullable();
                $table->integer('lag_periods')->default(0);
                $table->decimal('confidence', 5, 2)->nullable();
                $table->dateTime('last_computed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_industry_benchmarks')) {
            Schema::create('strategy_industry_benchmarks', function (Blueprint $table) {
                $table->id();
                $table->string('ratio_name')->index();
                $table->string('industry')->nullable()->index();
                $table->string('country', 8)->nullable()->index();
                $table->decimal('p25', 18, 4)->nullable();
                $table->decimal('median', 18, 4)->nullable();
                $table->decimal('p75', 18, 4)->nullable();
                $table->integer('year')->nullable();
                $table->string('source')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_alerts')) {
            Schema::create('strategy_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('type');
                $table->string('severity')->default('info');
                $table->text('message');
                $table->unsignedBigInteger('kpi_id')->nullable()->index();
                $table->unsignedBigInteger('ratio_id')->nullable()->index();
                $table->dateTime('triggered_at');
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_signals')) {
            Schema::create('strategy_signals', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('type');
                $table->string('source_module')->nullable();
                $table->string('source_metric')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('recommendation')->nullable();
                $table->json('impacted_objective_ids')->nullable();
                $table->boolean('is_read')->default(false);
                $table->boolean('is_dismissed')->default(false);
                $table->dateTime('detected_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_rituals')) {
            Schema::create('strategy_rituals', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('type')->nullable();
                $table->string('cadence')->nullable();
                $table->unsignedTinyInteger('day_of_week')->nullable();
                $table->unsignedTinyInteger('day_of_month')->nullable();
                $table->json('attendee_roles')->nullable();
                $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_ritual_sessions')) {
            Schema::create('strategy_ritual_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ritual_id')->index();
                $table->dateTime('scheduled_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->unsignedBigInteger('facilitator_id')->nullable()->index();
                $table->json('agenda')->nullable();
                $table->json('decisions')->nullable();
                $table->json('action_items')->nullable();
                $table->text('ai_summary')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_scenarios')) {
            Schema::create('strategy_scenarios', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('type')->nullable();
                $table->unsignedBigInteger('base_plan_id')->nullable()->index();
                $table->string('status')->default('draft');
                $table->decimal('probability', 5, 2)->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('strategy_scenario_assumptions')) {
            Schema::create('strategy_scenario_assumptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('scenario_id')->index();
                $table->string('variable_name');
                $table->text('description')->nullable();
                $table->decimal('base_value', 18, 4)->nullable();
                $table->decimal('adjusted_value', 18, 4)->nullable();
                $table->string('impact_scope')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_scenario_assumptions');
        Schema::dropIfExists('strategy_scenarios');
        Schema::dropIfExists('strategy_ritual_sessions');
        Schema::dropIfExists('strategy_rituals');
        Schema::dropIfExists('strategy_signals');
        Schema::dropIfExists('strategy_alerts');
        Schema::dropIfExists('strategy_industry_benchmarks');
        Schema::dropIfExists('strategy_correlations');
        Schema::dropIfExists('strategy_kros');
        Schema::dropIfExists('strategy_objective_links');
        Schema::dropIfExists('strategy_key_results');
        Schema::dropIfExists('strategy_objectives');
        Schema::dropIfExists('strategy_ratio_snapshots');
        Schema::dropIfExists('strategy_ratios');
        Schema::dropIfExists('strategy_kpi_values');
        Schema::dropIfExists('strategy_kpis');
        Schema::dropIfExists('strategy_pillars');
        Schema::dropIfExists('strategy_plans');
    }
};
