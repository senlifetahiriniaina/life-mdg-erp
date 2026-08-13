<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterializedViewService
{
    public const VIEWS = [
        'mv_sales_daily_summary',
        'mv_manufacturing_daily_kpis',
        'mv_project_health_summary',
        'mv_hr_metrics_summary',
        'mv_inventory_stock_summary',
        'mv_crm_pipeline_summary',
        'mv_document_analytics',
        'mv_email_campaign_analytics',
    ];

    public function refreshAll(): void
    {
        foreach (self::VIEWS as $view) {
            $this->refresh($view);
        }
    }

    public function refresh(string $view): void
    {
        try {
            $procedure = $this->getProcedureName($view);

            if ($this->procedureExists($procedure)) {
                DB::statement("CALL $procedure()");
                Log::info("Materialized view refreshed: $view");
            } else {
                $this->refreshManually($view);
            }
        } catch (\Exception $e) {
            Log::error("Failed to refresh materialized view $view: " . $e->getMessage());
        }
    }

    private function getProcedureName(string $view): string
    {
        return match($view) {
            'mv_sales_daily_summary' => 'refresh_sales_daily_summary',
            'mv_manufacturing_daily_kpis' => 'refresh_manufacturing_daily_kpis',
            'mv_project_health_summary' => 'refresh_project_health_summary',
            'mv_hr_metrics_summary' => 'refresh_hr_metrics_summary',
            'mv_inventory_stock_summary' => 'refresh_inventory_stock_summary',
            'mv_crm_pipeline_summary' => 'refresh_crm_pipeline_summary',
            'mv_document_analytics' => 'refresh_document_analytics',
            'mv_email_campaign_analytics' => 'refresh_email_campaign_analytics',
            default => 'refresh_' . str_replace('mv_', '', $view),
        };
    }

    private function procedureExists(string $procedure): bool
    {
        $result = DB::selectOne("
            SELECT COUNT(*) as count FROM information_schema.ROUTINES
            WHERE ROUTINE_NAME = ? AND ROUTINE_SCHEMA = DATABASE()
        ", [$procedure]);

        return ($result->count ?? 0) > 0;
    }

    private function refreshManually(string $view): void
    {
        // Fallback: Use raw SQL queries to refresh views
        DB::statement("DELETE FROM $view");

        match($view) {
            'mv_sales_daily_summary' => $this->refreshSalesSummary(),
            'mv_manufacturing_daily_kpis' => $this->refreshManufacturingKpis(),
            'mv_project_health_summary' => $this->refreshProjectHealth(),
            'mv_hr_metrics_summary' => $this->refreshHrMetrics(),
            'mv_inventory_stock_summary' => $this->refreshInventoryStock(),
            'mv_crm_pipeline_summary' => $this->refreshCrmPipeline(),
            'mv_document_analytics' => $this->refreshDocumentAnalytics(),
            'mv_email_campaign_analytics' => $this->refreshEmailAnalytics(),
            default => null,
        };
    }

    private function refreshSalesSummary(): void
    {
        DB::statement("
            INSERT INTO mv_sales_daily_summary
            (date, total_orders, total_revenue, avg_order_value, total_items, completed_orders, completed_revenue, refreshed_at)
            SELECT
                DATE(o.created_at) as date,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.grand_total) as total_revenue,
                AVG(o.grand_total) as avg_order_value,
                SUM(oi.quantity) as total_items,
                SUM(IF(o.status = 'completed', 1, 0)) as completed_orders,
                SUM(IF(o.status = 'completed', o.grand_total, 0)) as completed_revenue,
                NOW() as refreshed_at
            FROM ec_orders o
            LEFT JOIN ec_order_items oi ON o.id = oi.order_id
            GROUP BY DATE(o.created_at)
        ");
    }

    private function refreshManufacturingKpis(): void
    {
        DB::statement("
            INSERT INTO mv_manufacturing_daily_kpis
            (date, total_work_orders, in_progress_orders, completed_orders, avg_completion_rate,
             total_quality_checks, passed_checks, failed_checks, quality_pass_rate, refreshed_at)
            SELECT
                DATE(w.created_at) as date,
                COUNT(DISTINCT w.id) as total_work_orders,
                SUM(IF(w.status = 'in_progress', 1, 0)) as in_progress_orders,
                SUM(IF(w.status = 'completed', 1, 0)) as completed_orders,
                AVG(CASE WHEN w.status = 'completed'
                    THEN (w.quantity_produced / NULLIF(w.quantity_planned, 0)) * 100
                    ELSE 0 END) as avg_completion_rate,
                COUNT(DISTINCT q.id) as total_quality_checks,
                SUM(IF(q.status = 'passed', 1, 0)) as passed_checks,
                SUM(IF(q.status = 'failed', 1, 0)) as failed_checks,
                ROUND(100 * SUM(IF(q.status = 'passed', 1, 0)) / NULLIF(COUNT(DISTINCT q.id), 0), 2) as quality_pass_rate,
                NOW() as refreshed_at
            FROM mfg_work_orders w
            LEFT JOIN mfg_quality_checks q ON w.id = q.work_order_id
            GROUP BY DATE(w.created_at)
        ");
    }

    private function refreshProjectHealth(): void
    {
        DB::statement("
            INSERT INTO mv_project_health_summary
            (project_id, project_name, total_tasks, todo_tasks, in_progress_tasks, completed_tasks,
             completion_percentage, overdue_tasks, team_members, expected_end_date, refreshed_at)
            SELECT
                p.id,
                p.name,
                COUNT(DISTINCT t.id) as total_tasks,
                SUM(IF(t.status = 'todo', 1, 0)) as todo_tasks,
                SUM(IF(t.status = 'in_progress', 1, 0)) as in_progress_tasks,
                SUM(IF(t.status = 'done', 1, 0)) as completed_tasks,
                ROUND(100 * SUM(IF(t.status = 'done', 1, 0)) / NULLIF(COUNT(DISTINCT t.id), 0), 2) as completion_percentage,
                SUM(IF(t.due_date < NOW() AND t.status != 'done', 1, 0)) as overdue_tasks,
                COUNT(DISTINCT t.assignee_id) as team_members,
                p.expected_end_date,
                NOW() as refreshed_at
            FROM prj_projects p
            LEFT JOIN prj_tasks t ON p.id = t.project_id
            GROUP BY p.id
        ");
    }

    private function refreshHrMetrics(): void
    {
        DB::statement("
            INSERT INTO mv_hr_metrics_summary
            (period_date, total_employees, active_employees, on_leave, avg_utilization_rate,
             total_timesheets, total_hours, approved_timesheets, pending_approvals, refreshed_at)
            SELECT
                CURDATE(),
                COUNT(DISTINCT e.id) as total_employees,
                SUM(IF(e.employment_status = 'active', 1, 0)) as active_employees,
                SUM(IF(e.employment_status = 'on_leave', 1, 0)) as on_leave,
                AVG(CASE WHEN ts.id IS NOT NULL THEN ts.hours_worked / 40 * 100 ELSE 0 END) as avg_utilization_rate,
                COUNT(DISTINCT ts.id) as total_timesheets,
                SUM(COALESCE(ts.hours_worked, 0)) as total_hours,
                SUM(IF(ts.status = 'approved', 1, 0)) as approved_timesheets,
                SUM(IF(ts.status = 'pending', 1, 0)) as pending_approvals,
                NOW() as refreshed_at
            FROM hr_employees e
            LEFT JOIN hr_timesheets ts ON e.id = ts.employee_id AND DATE(ts.created_at) = CURDATE()
        ");
    }

    private function refreshInventoryStock(): void
    {
        DB::statement("
            INSERT INTO mv_inventory_stock_summary
            (product_id, product_name, sku, total_quantity, reserved_quantity, available_quantity,
             reorder_point, is_low_stock, warehouse_locations, last_movement, refreshed_at)
            SELECT
                p.id,
                p.name,
                p.sku,
                SUM(COALESCE(sl.quantity, 0)) as total_quantity,
                SUM(COALESCE(sl.reserved, 0)) as reserved_quantity,
                SUM(COALESCE(sl.quantity - sl.reserved, 0)) as available_quantity,
                p.reorder_point,
                IF(SUM(COALESCE(sl.quantity - sl.reserved, 0)) <= p.reorder_point, 1, 0) as is_low_stock,
                COUNT(DISTINCT sl.warehouse_location_id) as warehouse_locations,
                MAX(sl.updated_at) as last_movement,
                NOW() as refreshed_at
            FROM ec_products p
            LEFT JOIN inv_stock_levels sl ON p.id = sl.product_id
            GROUP BY p.id
        ");
    }

    private function refreshCrmPipeline(): void
    {
        DB::statement("
            INSERT INTO mv_crm_pipeline_summary
            (pipeline_id, pipeline_name, total_deals, pipeline_value, stage_count, avg_deal_size,
             win_rate, open_deals, open_value, refreshed_at)
            SELECT
                pipe.id,
                pipe.name,
                COUNT(DISTINCT d.id) as total_deals,
                SUM(COALESCE(d.value, 0)) as pipeline_value,
                COUNT(DISTINCT d.pipeline_stage_id) as stage_count,
                AVG(COALESCE(d.value, 0)) as avg_deal_size,
                ROUND(100 * SUM(IF(d.status = 'won', 1, 0)) / NULLIF(COUNT(DISTINCT d.id), 0), 2) as win_rate,
                SUM(IF(d.status = 'open', 1, 0)) as open_deals,
                SUM(IF(d.status = 'open', COALESCE(d.value, 0), 0)) as open_value,
                NOW() as refreshed_at
            FROM crm_pipelines pipe
            LEFT JOIN crm_deals d ON pipe.id = d.pipeline_id
            GROUP BY pipe.id
        ");
    }

    private function refreshDocumentAnalytics(): void
    {
        DB::statement("
            INSERT INTO mv_document_analytics
            (date, total_documents, new_documents, total_downloads, total_shares, unique_users, total_storage_bytes, refreshed_at)
            SELECT
                CURDATE(),
                COUNT(DISTINCT d.id) as total_documents,
                SUM(IF(DATE(d.created_at) = CURDATE(), 1, 0)) as new_documents,
                COUNT(DISTINCT da.id) as total_downloads,
                COUNT(DISTINCT IF(da.action = 'share', da.id, NULL)) as total_shares,
                COUNT(DISTINCT da.user_id) as unique_users,
                SUM(COALESCE(d.file_size, 0)) as total_storage_bytes,
                NOW() as refreshed_at
            FROM doc_documents d
            LEFT JOIN doc_audit_logs da ON d.id = da.document_id
        ");
    }

    private function refreshEmailAnalytics(): void
    {
        DB::statement("
            INSERT INTO mv_email_campaign_analytics
            (campaign_id, campaign_name, total_recipients, delivered, opened, clicked,
             open_rate, click_rate, unsubscribed, sent_date, refreshed_at)
            SELECT
                c.id,
                c.name,
                COUNT(DISTINCT r.id) as total_recipients,
                SUM(IF(r.delivery_status = 'delivered', 1, 0)) as delivered,
                SUM(IF(r.opened_at IS NOT NULL, 1, 0)) as opened,
                SUM(IF(r.clicked_at IS NOT NULL, 1, 0)) as clicked,
                ROUND(100 * SUM(IF(r.opened_at IS NOT NULL, 1, 0)) / NULLIF(COUNT(DISTINCT r.id), 0), 2) as open_rate,
                ROUND(100 * SUM(IF(r.clicked_at IS NOT NULL, 1, 0)) / NULLIF(COUNT(DISTINCT r.id), 0), 2) as click_rate,
                SUM(IF(r.unsubscribed_at IS NOT NULL, 1, 0)) as unsubscribed,
                c.sent_at,
                NOW() as refreshed_at
            FROM email_campaigns c
            LEFT JOIN email_recipients r ON c.id = r.campaign_id
            GROUP BY c.id
        ");
    }
}
