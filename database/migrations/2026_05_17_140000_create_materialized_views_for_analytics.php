<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Skip all materialized view setup in test environment
        // Check both app() and direct environment variable, plus phpunit.xml setting
        if (app()->environment('testing') || env('APP_ENV') === 'testing' || getenv('APP_ENV') === 'testing') {
            return;
        }

        // 1. Sales Summary View (Daily Revenue)
        if (!Schema::hasTable('mv_sales_daily_summary')) {
            Schema::create('mv_sales_daily_summary', function (Blueprint $table) {
                $table->date('date')->primary();
                $table->integer('total_orders')->default(0);
                $table->decimal('total_revenue', 15, 2)->default(0);
                $table->decimal('avg_order_value', 15, 2)->default(0);
                $table->integer('total_items')->default(0);
                $table->integer('completed_orders')->default(0);
                $table->decimal('completed_revenue', 15, 2)->default(0);
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('date');
            });
        }

        // 2. Manufacturing KPI View (Daily Production Metrics)
        if (!Schema::hasTable('mv_manufacturing_daily_kpis')) {
            Schema::create('mv_manufacturing_daily_kpis', function (Blueprint $table) {
                $table->date('date')->primary();
                $table->integer('total_work_orders')->default(0);
                $table->integer('in_progress_orders')->default(0);
                $table->integer('completed_orders')->default(0);
                $table->decimal('avg_completion_rate', 5, 2)->default(0);
                $table->integer('total_quality_checks')->default(0);
                $table->integer('passed_checks')->default(0);
                $table->integer('failed_checks')->default(0);
                $table->decimal('quality_pass_rate', 5, 2)->default(0);
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('date');
            });
        }

        // 3. Project Dashboard View (Project Health Summary)
        if (!Schema::hasTable('mv_project_health_summary')) {
            Schema::create('mv_project_health_summary', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->primary();
                $table->string('project_name', 255);
                $table->integer('total_tasks')->default(0);
                $table->integer('todo_tasks')->default(0);
                $table->integer('in_progress_tasks')->default(0);
                $table->integer('completed_tasks')->default(0);
                $table->decimal('completion_percentage', 5, 2)->default(0);
                $table->integer('overdue_tasks')->default(0);
                $table->integer('team_members')->default(0);
                $table->date('expected_end_date')->nullable();
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('project_id');
                $table->index('completion_percentage');
            });
        }

        // 4. HR Metrics View (Employee & Utilization Summary)
        if (!Schema::hasTable('mv_hr_metrics_summary')) {
            Schema::create('mv_hr_metrics_summary', function (Blueprint $table) {
                $table->date('period_date')->primary();
                $table->integer('total_employees')->default(0);
                $table->integer('active_employees')->default(0);
                $table->integer('on_leave')->default(0);
                $table->decimal('avg_utilization_rate', 5, 2)->default(0);
                $table->integer('total_timesheets')->default(0);
                $table->decimal('total_hours', 10, 2)->default(0);
                $table->integer('approved_timesheets')->default(0);
                $table->integer('pending_approvals')->default(0);
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('period_date');
            });
        }

        // 5. Inventory Status View (Stock Level Summary)
        if (!Schema::hasTable('mv_inventory_stock_summary')) {
            Schema::create('mv_inventory_stock_summary', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->primary();
                $table->string('product_name', 255);
                $table->string('sku', 100);
                $table->integer('total_quantity')->default(0);
                $table->integer('reserved_quantity')->default(0);
                $table->integer('available_quantity')->default(0);
                $table->integer('reorder_point')->default(0);
                $table->boolean('is_low_stock')->default(false);
                $table->integer('warehouse_locations')->default(0);
                $table->timestamp('last_movement')->nullable();
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('product_id');
                $table->index('is_low_stock');
                $table->index('available_quantity');
            });
        }

        // 6. CRM Pipeline View (Sales Pipeline Summary)
        if (!Schema::hasTable('mv_crm_pipeline_summary')) {
            Schema::create('mv_crm_pipeline_summary', function (Blueprint $table) {
                $table->unsignedBigInteger('pipeline_id')->primary();
                $table->string('pipeline_name', 255);
                $table->integer('total_deals')->default(0);
                $table->decimal('pipeline_value', 15, 2)->default(0);
                $table->integer('stage_count')->default(0);
                $table->decimal('avg_deal_size', 15, 2)->default(0);
                $table->decimal('win_rate', 5, 2)->default(0);
                $table->integer('open_deals')->default(0);
                $table->decimal('open_value', 15, 2)->default(0);
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('pipeline_id');
                $table->index('pipeline_value');
            });
        }

        // 7. Document Analytics View (Document Usage Summary)
        if (!Schema::hasTable('mv_document_analytics')) {
            Schema::create('mv_document_analytics', function (Blueprint $table) {
                $table->date('date')->primary();
                $table->integer('total_documents')->default(0);
                $table->integer('new_documents')->default(0);
                $table->integer('total_downloads')->default(0);
                $table->integer('total_shares')->default(0);
                $table->integer('unique_users')->default(0);
                $table->bigInteger('total_storage_bytes')->default(0);
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('date');
            });
        }

        // 8. Email Campaign Analytics View
        if (!Schema::hasTable('mv_email_campaign_analytics')) {
            Schema::create('mv_email_campaign_analytics', function (Blueprint $table) {
                $table->unsignedBigInteger('campaign_id')->primary();
                $table->string('campaign_name', 255);
                $table->integer('total_recipients')->default(0);
                $table->integer('delivered')->default(0);
                $table->integer('opened')->default(0);
                $table->integer('clicked')->default(0);
                $table->decimal('open_rate', 5, 2)->default(0);
                $table->decimal('click_rate', 5, 2)->default(0);
                $table->integer('unsubscribed')->default(0);
                $table->timestamp('sent_date')->nullable();
                $table->timestamp('refreshed_at')->useCurrent();
                $table->index('campaign_id');
                $table->index('open_rate');
            });
        }

        // Create refresh procedures (wrapped for safety)
        try {
            $this->createRefreshProcedures();
        } catch (\Throwable $e) {
            // Log but don't fail migration if procedures fail to create
            $isTest = app()->environment('testing') || getenv('APP_ENV') === 'testing';
            if (!$isTest) {
                \Log::warning('Failed to create refresh procedures: ' . $e->getMessage());
            }
        }
    }

    private function createRefreshProcedures(): void
    {
        // Skip procedure creation in test environment (simplifies test database setup)
        if (app()->environment('testing') || env('APP_ENV') === 'testing') {
            return;
        }

        try {
            // Sales Daily Summary Refresh
            DB::unprepared('
            CREATE PROCEDURE refresh_sales_daily_summary()
            LANGUAGE SQL
            BEGIN
                INSERT INTO mv_sales_daily_summary (date, total_orders, total_revenue, avg_order_value, total_items, completed_orders, completed_revenue, refreshed_at)
                SELECT
                    DATE(o.created_at) as date,
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(o.grand_total) as total_revenue,
                    AVG(o.grand_total) as avg_order_value,
                    SUM(oi.quantity) as total_items,
                    SUM(IF(o.status = "completed", 1, 0)) as completed_orders,
                    SUM(IF(o.status = "completed", o.grand_total, 0)) as completed_revenue,
                    NOW() as refreshed_at
                FROM ec_orders o
                LEFT JOIN ec_order_items oi ON o.id = oi.order_id
                GROUP BY DATE(o.created_at)
                ON DUPLICATE KEY UPDATE
                    total_orders = VALUES(total_orders),
                    total_revenue = VALUES(total_revenue),
                    avg_order_value = VALUES(avg_order_value),
                    total_items = VALUES(total_items),
                    completed_orders = VALUES(completed_orders),
                    completed_revenue = VALUES(completed_revenue),
                    refreshed_at = NOW();
            END
        ');

        // Manufacturing KPI Refresh
        DB::unprepared('
            CREATE PROCEDURE refresh_manufacturing_daily_kpis()
            LANGUAGE SQL
            BEGIN
                INSERT INTO mv_manufacturing_daily_kpis (date, total_work_orders, in_progress_orders, completed_orders, avg_completion_rate, total_quality_checks, passed_checks, failed_checks, quality_pass_rate, refreshed_at)
                SELECT
                    DATE(w.created_at) as date,
                    COUNT(DISTINCT w.id) as total_work_orders,
                    SUM(IF(w.status = "in_progress", 1, 0)) as in_progress_orders,
                    SUM(IF(w.status = "completed", 1, 0)) as completed_orders,
                    AVG(CASE WHEN w.status = "completed" THEN (w.quantity_produced / w.quantity_planned) * 100 ELSE 0 END) as avg_completion_rate,
                    COUNT(DISTINCT q.id) as total_quality_checks,
                    SUM(IF(q.status = "passed", 1, 0)) as passed_checks,
                    SUM(IF(q.status = "failed", 1, 0)) as failed_checks,
                    ROUND(100 * SUM(IF(q.status = "passed", 1, 0)) / NULLIF(COUNT(DISTINCT q.id), 0), 2) as quality_pass_rate,
                    NOW() as refreshed_at
                FROM mfg_work_orders w
                LEFT JOIN mfg_quality_checks q ON w.id = q.work_order_id
                GROUP BY DATE(w.created_at)
                ON DUPLICATE KEY UPDATE
                    total_work_orders = VALUES(total_work_orders),
                    in_progress_orders = VALUES(in_progress_orders),
                    completed_orders = VALUES(completed_orders),
                    avg_completion_rate = VALUES(avg_completion_rate),
                    total_quality_checks = VALUES(total_quality_checks),
                    passed_checks = VALUES(passed_checks),
                    failed_checks = VALUES(failed_checks),
                    quality_pass_rate = VALUES(quality_pass_rate),
                    refreshed_at = NOW();
            END
        ');
        } catch (\Exception $e) {
            // Log but don't fail migration if procedure creation fails
            // This can happen if required tables don't exist yet
            \Log::warning('Failed to create stored procedures: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Skip in test environment
        if (app()->environment('testing') || getenv('APP_ENV') === 'testing') {
            return;
        }

        try {
            DB::unprepared('DROP PROCEDURE IF EXISTS refresh_sales_daily_summary');
            DB::unprepared('DROP PROCEDURE IF EXISTS refresh_manufacturing_daily_kpis');
        } catch (\Exception $e) {
            // Silently ignore if procedures don't exist (especially in testing)
        }

        Schema::dropIfExists('mv_sales_daily_summary');
        Schema::dropIfExists('mv_manufacturing_daily_kpis');
        Schema::dropIfExists('mv_project_health_summary');
        Schema::dropIfExists('mv_hr_metrics_summary');
        Schema::dropIfExists('mv_inventory_stock_summary');
        Schema::dropIfExists('mv_crm_pipeline_summary');
        Schema::dropIfExists('mv_document_analytics');
        Schema::dropIfExists('mv_email_campaign_analytics');
    }
};
