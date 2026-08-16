<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BudgetVarianceService (and Budget::budgetActuals()/budgetAlerts(),
 * BudgetLine::budgetActuals(), GLAccount::glEntries()) have referenced
 * Modules\Accounting\Models\{BudgetActual,BudgetAlert,BudgetForecast,GLEntry}
 * since they were written, but none of the four classes ever existed.
 * acc_budget_actuals/acc_budget_alerts/acc_budget_forecasts DO exist as
 * tables — but only as generic stubs ({id, tenant_id, data blob,
 * timestamps, deleted_at}) from an earlier bulk "create every missing
 * accounting table" pass, none of the columns the service actually reads/
 * writes. acc_gl_entries never existed at all. Every variance-report/
 * monthly-trend/top-variances/alert/forecast endpoint fatals the moment it
 * runs. Life MDG needs real budget-vs-actual tracking (Strategy First
 * cockpit, OHADA budget control), so this patches the three stubs with
 * their real columns and creates the one genuinely missing table — column
 * names derived directly from how the service and models use them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acc_budget_actuals')) {
            Schema::table('acc_budget_actuals', function (Blueprint $table) {
                if (! Schema::hasColumn('acc_budget_actuals', 'budget_id')) {
                    $table->unsignedBigInteger('budget_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('acc_budget_actuals', 'budget_line_id')) {
                    $table->unsignedBigInteger('budget_line_id')->nullable()->after('budget_id');
                }
                if (! Schema::hasColumn('acc_budget_actuals', 'period_month')) {
                    $table->date('period_month')->nullable()->after('budget_line_id');
                }
                if (! Schema::hasColumn('acc_budget_actuals', 'actual_amount')) {
                    $table->decimal('actual_amount', 15, 4)->default(0)->after('period_month');
                }
            });
            Schema::table('acc_budget_actuals', function (Blueprint $table) {
                if (! Schema::hasIndex('acc_budget_actuals', 'acc_budget_actuals_budget_id_period_month_index')) {
                    $table->index(['budget_id', 'period_month']);
                }
                if (! Schema::hasIndex('acc_budget_actuals', 'acc_budget_actuals_budget_line_id_period_month_index')) {
                    $table->index(['budget_line_id', 'period_month']);
                }
            });
        }

        if (Schema::hasTable('acc_budget_alerts')) {
            Schema::table('acc_budget_alerts', function (Blueprint $table) {
                if (! Schema::hasColumn('acc_budget_alerts', 'budget_id')) {
                    $table->unsignedBigInteger('budget_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'budget_line_id')) {
                    $table->unsignedBigInteger('budget_line_id')->nullable()->after('budget_id');
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'alert_type')) {
                    $table->string('alert_type')->nullable()->after('budget_line_id'); // over_budget | approaching_limit | variance
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'status')) {
                    $table->string('status')->default('triggered')->after('alert_type');
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'threshold_percent')) {
                    $table->decimal('threshold_percent', 6, 2)->default(0)->after('status');
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'current_variance_percent')) {
                    $table->decimal('current_variance_percent', 8, 2)->default(0)->after('threshold_percent');
                }
                if (! Schema::hasColumn('acc_budget_alerts', 'triggered_at')) {
                    $table->timestamp('triggered_at')->nullable()->after('current_variance_percent');
                }
            });
            Schema::table('acc_budget_alerts', function (Blueprint $table) {
                if (! Schema::hasIndex('acc_budget_alerts', 'acc_budget_alerts_budget_id_alert_type_status_index')) {
                    $table->index(['budget_id', 'alert_type', 'status']);
                }
            });
        }

        if (Schema::hasTable('acc_budget_forecasts')) {
            Schema::table('acc_budget_forecasts', function (Blueprint $table) {
                if (! Schema::hasColumn('acc_budget_forecasts', 'budget_id')) {
                    $table->unsignedBigInteger('budget_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('acc_budget_forecasts', 'budget_line_id')) {
                    $table->unsignedBigInteger('budget_line_id')->nullable()->after('budget_id');
                }
                if (! Schema::hasColumn('acc_budget_forecasts', 'forecast_month')) {
                    $table->date('forecast_month')->nullable()->after('budget_line_id');
                }
                if (! Schema::hasColumn('acc_budget_forecasts', 'forecasted_amount')) {
                    $table->decimal('forecasted_amount', 15, 4)->default(0)->after('forecast_month');
                }
                if (! Schema::hasColumn('acc_budget_forecasts', 'forecast_method')) {
                    $table->string('forecast_method')->default('linear')->after('forecasted_amount');
                }
                if (! Schema::hasColumn('acc_budget_forecasts', 'confidence_level')) {
                    $table->decimal('confidence_level', 4, 2)->default(0.5)->after('forecast_method');
                }
            });
            Schema::table('acc_budget_forecasts', function (Blueprint $table) {
                if (! Schema::hasIndex('acc_budget_forecasts', 'acc_budget_forecasts_budget_line_id_forecast_month_unique')) {
                    $table->unique(['budget_line_id', 'forecast_month']);
                }
            });
        }

        if (! Schema::hasTable('acc_gl_entries')) {
            Schema::create('acc_gl_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gl_account_id')->constrained('acc_gl_accounts')->cascadeOnDelete();
                $table->date('entry_date');
                $table->decimal('debit_amount', 15, 4)->default(0);
                $table->decimal('credit_amount', 15, 4)->default(0);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['gl_account_id', 'entry_date']);
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_gl_entries');

        if (Schema::hasTable('acc_budget_forecasts')) {
            Schema::table('acc_budget_forecasts', function (Blueprint $table) {
                foreach (['budget_id', 'budget_line_id', 'forecast_month', 'forecasted_amount', 'forecast_method', 'confidence_level'] as $column) {
                    if (Schema::hasColumn('acc_budget_forecasts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('acc_budget_alerts')) {
            Schema::table('acc_budget_alerts', function (Blueprint $table) {
                foreach (['budget_id', 'budget_line_id', 'alert_type', 'status', 'threshold_percent', 'current_variance_percent', 'triggered_at'] as $column) {
                    if (Schema::hasColumn('acc_budget_alerts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('acc_budget_actuals')) {
            Schema::table('acc_budget_actuals', function (Blueprint $table) {
                foreach (['budget_id', 'budget_line_id', 'period_month', 'actual_amount'] as $column) {
                    if (Schema::hasColumn('acc_budget_actuals', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
