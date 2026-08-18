<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8 (AuditLog) — closes a real cross-tenant leak: core_audit_logs
 * (the live, actually-populated audit trail written by RecordsActivity /
 * AuditableActions / AuditAuthListener across the whole app) had no
 * company_id/tenant_id column at all, so AuditLogApiController /
 * AuditLogWebController could never scope a query by tenant even if they
 * wanted to — any user holding auditlog.logs.view* could browse every
 * company's full audit trail.
 *
 * Adds a nullable company_id (unsignedBigInteger, no FK — matching the
 * established pattern for every other *_company_id patch migration in this
 * repo, e.g. 2026_08_16_000004_add_company_id_to_acc_expense_reports_table;
 * deliberately no FK here since real rows are backfilled to the literal
 * sentinel 0 below, which is never a real companies.id) and backfills every
 * existing row from its own user_id -> users.company_id, the only tenant
 * context an audit-log row carries. Rows with no resolvable company
 * (system/tenant-less users, user_id null) are normalized to 0 rather than
 * left NULL — matching the `$user->company_id ?? 0` fallback-to-0 convention
 * already established for Reporting/Strategy's own company_id scoping in this
 * same chantier session (see CLAUDE.md), so every row is a real, comparable
 * integer and controller queries never have to special-case NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('core_audit_logs', 'company_id')) {
            Schema::table('core_audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('user_id');
            });

            Schema::table('core_audit_logs', function (Blueprint $table) {
                $table->index(['company_id', 'created_at']);
            });
        }

        // Backfill from the row's own user_id -> users.company_id — the only
        // tenant context an existing audit-log row carries. Anything left
        // unresolved (no user_id, or the user itself has no company_id) is
        // normalized to 0, the same tenant-less sentinel the traits/
        // controllers below now write/query going forward.
        $driver = DB::connection()->getDriverName();

        if (Schema::hasColumn('users', 'company_id')) {
            if ($driver === 'sqlite') {
                DB::statement(
                    "UPDATE core_audit_logs
                     SET company_id = COALESCE(
                         (SELECT users.company_id FROM users WHERE users.id = core_audit_logs.user_id), 0
                     )
                     WHERE core_audit_logs.company_id IS NULL"
                );
            } else {
                DB::statement(
                    'UPDATE core_audit_logs
                     LEFT JOIN users ON users.id = core_audit_logs.user_id
                     SET core_audit_logs.company_id = COALESCE(users.company_id, 0)
                     WHERE core_audit_logs.company_id IS NULL'
                );
            }
        } else {
            DB::table('core_audit_logs')->whereNull('company_id')->update(['company_id' => 0]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('core_audit_logs') || ! Schema::hasColumn('core_audit_logs', 'company_id')) {
            return;
        }

        Schema::table('core_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'created_at']);
            $table->dropColumn('company_id');
        });
    }
};
