<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `2026_05_29_000003_create_all_missing_module_tables.php` created 13 `bi_*`
 * tables as bare stubs (`id/tenant_id/name/config/timestamps/deleted_at`).
 * Only `bi_kpis`, `bi_dashboards`, and `bi_reports` ever received follow-up
 * column patches — the other 10 were left stub-only despite their Eloquent
 * models (`BiDataSource`, `Widget`, `BiQuery`, `BiAlert`, `KpiAlert`,
 * `ScheduledReport`, `PredictiveModel`, `BiAnomaly`, `AlertEvent`,
 * `Forecast`) declaring real `$fillable`/`$casts` against columns that never
 * existed. Chantier 8.2's BI audit confirmed this isn't dormant scaffold —
 * these are the tables behind 5 real, routed web pages (`/bi`,
 * `/bi/sql-editor`, `/bi/alerts`, `/bi/data-sources`,
 * `/bi/dashboards/builder`) and most of the BI API surface, all of which
 * currently crash with "unknown column" SQL errors. `bi_dashboards` and
 * `bi_reports` also still had a few columns each missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bi_dashboards')) {
            Schema::table('bi_dashboards', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_dashboards', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_dashboards', 'is_default')) {
                    $table->boolean('is_default')->default(false);
                }
            });
        }

        if (Schema::hasTable('bi_reports')) {
            Schema::table('bi_reports', function (Blueprint $table) {
                foreach (['query_config', 'chart_config', 'filters', 'schedule_recipients'] as $c) {
                    if (! Schema::hasColumn('bi_reports', $c)) {
                        $table->json($c)->nullable();
                    }
                }
                if (! Schema::hasColumn('bi_reports', 'schedule')) {
                    $table->string('schedule')->nullable();
                }
                if (! Schema::hasColumn('bi_reports', 'last_run_at')) {
                    $table->timestamp('last_run_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('bi_data_sources')) {
            Schema::table('bi_data_sources', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_data_sources', 'type')) {
                    $table->string('type')->nullable();
                }
                if (! Schema::hasColumn('bi_data_sources', 'connection_config')) {
                    $table->text('connection_config')->nullable();
                }
                if (! Schema::hasColumn('bi_data_sources', 'status')) {
                    $table->string('status')->nullable();
                }
                if (! Schema::hasColumn('bi_data_sources', 'last_tested_at')) {
                    $table->timestamp('last_tested_at')->nullable();
                }
                if (! Schema::hasColumn('bi_data_sources', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('bi_widgets')) {
            Schema::table('bi_widgets', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_widgets', 'dashboard_id')) {
                    $table->unsignedBigInteger('dashboard_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_widgets', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('bi_widgets', 'type')) {
                    $table->string('type')->nullable();
                }
                if (! Schema::hasColumn('bi_widgets', 'position')) {
                    $table->json('position')->nullable();
                }
                if (! Schema::hasColumn('bi_widgets', 'refresh_interval')) {
                    $table->unsignedInteger('refresh_interval')->default(0);
                }
            });
        }

        if (Schema::hasTable('bi_queries')) {
            Schema::table('bi_queries', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_queries', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_queries', 'sql_query')) {
                    $table->text('sql_query')->nullable();
                }
                if (! Schema::hasColumn('bi_queries', 'datasource')) {
                    $table->string('datasource')->nullable();
                }
                if (! Schema::hasColumn('bi_queries', 'result_cache_ttl')) {
                    $table->unsignedInteger('result_cache_ttl')->default(0);
                }
                if (! Schema::hasColumn('bi_queries', 'is_public')) {
                    $table->boolean('is_public')->default(false);
                }
                if (! Schema::hasColumn('bi_queries', 'last_run_at')) {
                    $table->timestamp('last_run_at')->nullable();
                }
            });
        }

        if (Schema::hasTable('bi_alerts')) {
            Schema::table('bi_alerts', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_alerts', 'widget_id')) {
                    $table->unsignedBigInteger('widget_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_alerts', 'query_id')) {
                    $table->unsignedBigInteger('query_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_alerts', 'condition_type')) {
                    $table->string('condition_type')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'threshold')) {
                    $table->decimal('threshold', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'metric_name')) {
                    $table->string('metric_name')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'check_interval_minutes')) {
                    $table->unsignedInteger('check_interval_minutes')->default(15);
                }
                if (! Schema::hasColumn('bi_alerts', 'channels')) {
                    $table->json('channels')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'recipients')) {
                    $table->json('recipients')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'status')) {
                    $table->string('status')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_alerts', 'last_checked_at')) {
                    $table->timestamp('last_checked_at')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'last_triggered_at')) {
                    $table->timestamp('last_triggered_at')->nullable();
                }
                if (! Schema::hasColumn('bi_alerts', 'last_value')) {
                    $table->decimal('last_value', 15, 4)->nullable();
                }
            });
        }

        if (Schema::hasTable('bi_kpi_alerts')) {
            Schema::table('bi_kpi_alerts', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_kpi_alerts', 'kpi_id')) {
                    $table->unsignedBigInteger('kpi_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'metric_name')) {
                    $table->string('metric_name')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'condition')) {
                    $table->string('condition')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'threshold')) {
                    $table->decimal('threshold', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'comparison_value')) {
                    $table->decimal('comparison_value', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'severity')) {
                    $table->string('severity')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'last_triggered_at')) {
                    $table->timestamp('last_triggered_at')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'trigger_count')) {
                    $table->unsignedInteger('trigger_count')->default(0);
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'notification_channels')) {
                    $table->json('notification_channels')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'recipients')) {
                    $table->json('recipients')->nullable();
                }
                if (! Schema::hasColumn('bi_kpi_alerts', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('bi_scheduled_reports')) {
            Schema::table('bi_scheduled_reports', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_scheduled_reports', 'report_id')) {
                    $table->unsignedBigInteger('report_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'schedule')) {
                    $table->string('schedule')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'day_of_week')) {
                    $table->unsignedTinyInteger('day_of_week')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'day_of_month')) {
                    $table->unsignedTinyInteger('day_of_month')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'format')) {
                    $table->string('format')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'recipients')) {
                    $table->json('recipients')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'last_sent_at')) {
                    $table->timestamp('last_sent_at')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'next_send_at')) {
                    $table->timestamp('next_send_at')->nullable();
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'send_count')) {
                    $table->unsignedInteger('send_count')->default(0);
                }
                if (! Schema::hasColumn('bi_scheduled_reports', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('bi_predictive_models')) {
            Schema::table('bi_predictive_models', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_predictive_models', 'entity_type')) {
                    $table->string('entity_type')->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'model_type')) {
                    $table->string('model_type')->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'training_data')) {
                    $table->json('training_data')->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'coefficients')) {
                    $table->json('coefficients')->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'accuracy_score')) {
                    $table->decimal('accuracy_score', 8, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'last_trained_at')) {
                    $table->timestamp('last_trained_at')->nullable();
                }
                if (! Schema::hasColumn('bi_predictive_models', 'forecast_horizon_days')) {
                    $table->unsignedInteger('forecast_horizon_days')->default(30);
                }
                if (! Schema::hasColumn('bi_predictive_models', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }

        if (Schema::hasTable('bi_anomalies')) {
            Schema::table('bi_anomalies', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_anomalies', 'entity_type')) {
                    $table->string('entity_type')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'entity_id')) {
                    $table->unsignedBigInteger('entity_id')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'metric_name')) {
                    $table->string('metric_name')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'detected_at')) {
                    $table->timestamp('detected_at')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'anomaly_date')) {
                    $table->date('anomaly_date')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'expected_value')) {
                    $table->decimal('expected_value', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'actual_value')) {
                    $table->decimal('actual_value', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'deviation_percent')) {
                    $table->decimal('deviation_percent', 8, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'severity')) {
                    $table->string('severity')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'status')) {
                    $table->string('status')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_anomalies', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable();
                }
                if (! Schema::hasColumn('bi_anomalies', 'acknowledged_by')) {
                    $table->unsignedBigInteger('acknowledged_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('bi_alert_events')) {
            Schema::table('bi_alert_events', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_alert_events', 'alert_id')) {
                    $table->unsignedBigInteger('alert_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_alert_events', 'triggered_value')) {
                    $table->decimal('triggered_value', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_alert_events', 'threshold')) {
                    $table->decimal('threshold', 15, 4)->nullable();
                }
                if (! Schema::hasColumn('bi_alert_events', 'message')) {
                    $table->text('message')->nullable();
                }
                if (! Schema::hasColumn('bi_alert_events', 'severity')) {
                    $table->string('severity')->nullable();
                }
                if (! Schema::hasColumn('bi_alert_events', 'acknowledged')) {
                    $table->boolean('acknowledged')->default(false);
                }
                if (! Schema::hasColumn('bi_alert_events', 'acknowledged_at')) {
                    $table->timestamp('acknowledged_at')->nullable();
                }
                if (! Schema::hasColumn('bi_alert_events', 'acknowledged_by')) {
                    $table->unsignedBigInteger('acknowledged_by')->nullable();
                }
            });
        }

        if (Schema::hasTable('bi_forecasts')) {
            Schema::table('bi_forecasts', function (Blueprint $table) {
                if (! Schema::hasColumn('bi_forecasts', 'predictive_model_id')) {
                    $table->unsignedBigInteger('predictive_model_id')->nullable()->index();
                }
                if (! Schema::hasColumn('bi_forecasts', 'forecast_date')) {
                    $table->date('forecast_date')->nullable();
                }
                if (! Schema::hasColumn('bi_forecasts', 'forecast_value')) {
                    $table->decimal('forecast_value', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_forecasts', 'lower_bound')) {
                    $table->decimal('lower_bound', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_forecasts', 'upper_bound')) {
                    $table->decimal('upper_bound', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_forecasts', 'actual_value')) {
                    $table->decimal('actual_value', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('bi_forecasts', 'error_percent')) {
                    $table->decimal('error_percent', 8, 4)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Additive-only patch on shared stub tables — no down() to avoid
        // dropping columns other patches on the same tables may depend on.
    }
};
