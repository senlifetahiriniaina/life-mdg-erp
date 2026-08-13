<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Links a BI widget to a strategy objective / KPI target
        if (!Schema::hasTable('bi_widget_objectives')) {
            Schema::create('bi_widget_objectives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('widget_id')->index();
                $table->string('kpi_key', 128);           // e.g. 'CRM:win_rate'
                $table->string('objective_label', 256);    // human label
                $table->decimal('target_value', 18, 4)->nullable();
                $table->string('target_unit', 32)->nullable(); // '%', 'XOF', 'x/an'
                $table->string('horizon', 16)->default('30d'); // '7d','30d','90d','1y'
                $table->date('horizon_start')->nullable();
                $table->date('horizon_end')->nullable();
                $table->string('status', 32)->default('active'); // active|achieved|at_risk|missed
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }

        // Links a BI widget to a forecast data source for comparison
        if (!Schema::hasTable('bi_widget_forecasts')) {
            Schema::create('bi_widget_forecasts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('widget_id')->index();
                $table->string('forecast_source', 128);    // 'analytics.demand' | 'accounting.cashflow' | table name
                $table->string('forecast_column', 128);    // column with forecast value
                $table->string('actual_column', 128);      // column with actual value
                $table->string('date_column', 128)->default('date');
                $table->string('label', 128)->default('Prévision');
                $table->string('horizon', 16)->default('30d');
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_widget_forecasts');
        Schema::dropIfExists('bi_widget_objectives');
    }
};
