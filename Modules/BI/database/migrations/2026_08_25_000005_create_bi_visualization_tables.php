<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.2 (BI) — Visualization subsystem. `VisualizationController` (12
 * methods — custom heatmap/3D/waterfall visualizations: create/update/delete,
 * export, share, render, performance tracking) and `VisualizationPolicy` are
 * both fully written and call into `Modules\BI\Models\{CustomVisualization,
 * VisualizationTemplate,VisualizationPerformance}`, which all have correct
 * $fillable/$casts but no migration ever created their tables. Creates all 3
 * now, matching every other model-with-no-table gap already fixed elsewhere
 * in this codebase (see Helpdesk's `2026_08_23_000001_create_remaining_cs_ai_tables.php`).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('bi_visualization_templates')) {
            Schema::create('bi_visualization_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('chart_type', 32)->index();
                $table->json('default_config');
                $table->json('color_scheme')->nullable();
                $table->boolean('is_public')->default(false)->index();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_custom_visualizations')) {
            Schema::create('bi_custom_visualizations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->index();
                $table->unsignedBigInteger('dashboard_id')->nullable()->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('type', 32)->index();
                $table->json('config');
                $table->json('data_source');
                $table->json('color_scale')->nullable();
                $table->json('range_config')->nullable();
                $table->boolean('real_time_enabled')->default(false);
                $table->unsignedInteger('refresh_interval')->default(60);
                $table->unsignedInteger('performance_score')->nullable();
                $table->decimal('avg_render_time', 10, 3)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_visualization_performance')) {
            Schema::create('bi_visualization_performance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('visualization_id')->index();
                $table->decimal('render_time', 10, 3);
                $table->unsignedInteger('data_points')->default(0);
                $table->decimal('memory_usage', 10, 2)->nullable();
                $table->decimal('cpu_usage', 10, 2)->nullable();
                $table->string('status', 32)->index();
                $table->text('error_message')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_visualization_performance');
        Schema::dropIfExists('bi_custom_visualizations');
        Schema::dropIfExists('bi_visualization_templates');
    }
};
