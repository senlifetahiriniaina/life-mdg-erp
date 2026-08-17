<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.2 (BI — Forecasting subsystem): ForecastingController (15
 * methods — forecast model create/train/deploy/archive/delete, predictions,
 * scenario planning, seasonality/trend analysis, accuracy/retraining) and
 * ForecastingPolicy are fully written and call into 7 real models
 * (ForecastModel, ForecastPrediction, ForecastScenario, ModelRetrainingLog,
 * ScenarioPrediction, SeasonalityPattern, TrendAnalysis) with correct
 * $fillable/$casts, but none of the 7 tables were ever created — the
 * controller was unreachable end-to-end. This is a separate, more advanced
 * subsystem from the already-working PredictiveModel/Forecast pair behind
 * /api/v1/bi/predictive-models — do not confuse the two. Creates all 7 now,
 * matching every other model-with-no-table gap already fixed elsewhere in
 * this codebase (see Helpdesk's 2026_08_23_000001_create_remaining_cs_ai_tables
 * and BI's own 2026_08_25_000002_create_bi_data_story_tables).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('bi_forecast_models')) {
            Schema::create('bi_forecast_models', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('model_type', 32);
                $table->string('status', 32)->default('draft')->index();
                $table->string('metric_name');
                $table->unsignedBigInteger('metric_source_id');
                $table->string('data_frequency', 16)->default('daily');
                $table->unsignedInteger('lookback_days')->default(30);
                $table->unsignedInteger('forecast_horizon')->default(7);
                $table->json('model_parameters')->nullable();
                $table->decimal('rmse', 14, 4)->nullable();
                $table->decimal('mae', 14, 4)->nullable();
                $table->decimal('mape', 8, 2)->nullable();
                $table->decimal('r_squared', 8, 4)->nullable();
                $table->timestamp('trained_at')->nullable();
                $table->timestamp('last_retrained_at')->nullable();
                $table->timestamp('next_retraining_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_forecast_predictions')) {
            Schema::create('bi_forecast_predictions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('model_id')->index();
                $table->timestamp('prediction_date')->index();
                $table->decimal('predicted_value', 18, 4);
                $table->decimal('lower_bound', 18, 4)->nullable();
                $table->decimal('upper_bound', 18, 4)->nullable();
                $table->decimal('confidence_level', 5, 2)->nullable();
                $table->decimal('actual_value', 18, 4)->nullable();
                $table->decimal('error_percent', 8, 4)->nullable();
                $table->boolean('is_outlier')->default(false);
                $table->json('feature_importance')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_forecast_scenarios')) {
            Schema::create('bi_forecast_scenarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('model_id')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('scenario_type', 32);
                $table->json('parameters')->nullable();
                $table->decimal('growth_rate_adjustment', 8, 2)->nullable();
                $table->decimal('volatility_adjustment', 8, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_model_retraining_logs')) {
            Schema::create('bi_model_retraining_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('model_id')->index();
                $table->string('status', 32)->default('in_progress')->index();
                $table->decimal('rmse', 14, 4)->nullable();
                $table->decimal('mae', 14, 4)->nullable();
                $table->decimal('mape', 8, 2)->nullable();
                $table->unsignedInteger('training_duration_seconds')->nullable();
                $table->unsignedInteger('records_processed')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_scenario_predictions')) {
            Schema::create('bi_scenario_predictions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('scenario_id')->index();
                $table->timestamp('prediction_date')->index();
                $table->decimal('predicted_value', 18, 4);
                $table->decimal('lower_bound', 18, 4)->nullable();
                $table->decimal('upper_bound', 18, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_seasonality_patterns')) {
            Schema::create('bi_seasonality_patterns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('model_id')->index();
                $table->string('pattern_type', 16);
                $table->json('seasonal_factors')->nullable();
                $table->decimal('strength', 6, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_trend_analysis')) {
            Schema::create('bi_trend_analysis', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('model_id')->index();
                $table->decimal('trend_slope', 14, 4)->nullable();
                $table->string('trend_direction', 16)->nullable();
                $table->decimal('trend_strength', 6, 4)->nullable();
                $table->unsignedInteger('change_points_count')->default(0);
                $table->json('change_point_dates')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_trend_analysis');
        Schema::dropIfExists('bi_seasonality_patterns');
        Schema::dropIfExists('bi_scenario_predictions');
        Schema::dropIfExists('bi_model_retraining_logs');
        Schema::dropIfExists('bi_forecast_scenarios');
        Schema::dropIfExists('bi_forecast_predictions');
        Schema::dropIfExists('bi_forecast_models');
    }
};
