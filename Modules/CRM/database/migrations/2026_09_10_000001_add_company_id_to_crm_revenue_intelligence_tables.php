<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 10 (CRM cross-tenant leak): crm_revenue_insights/crm_revenue_trends/
 * crm_revenue_anomalies had no company_id (or any tenant column at all), and
 * RevenueIntelligenceController never filtered any of its queries by tenant — any
 * authenticated user of any company could read (and, worse, resolve) every other
 * company's revenue insights/trends/anomalies. Additive nullable column, same
 * convention used throughout this session for the same bug class (e.g. AuditLog's
 * core_audit_logs company_id patch).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_revenue_insights', 'crm_revenue_trends', 'crm_revenue_anomalies'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->unsignedBigInteger('company_id')->nullable()->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['crm_revenue_insights', 'crm_revenue_trends', 'crm_revenue_anomalies'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
