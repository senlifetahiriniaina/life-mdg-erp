<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 31 (Validation re-audit, empirical-execution methodology):
 * validation_approval_requests had NO tenant/company column of any kind
 * despite existing solely to gate access to per-tenant business records
 * (invoices, purchase orders, ...) — confirmed empirically via a real
 * cross-company HTTP request that any authenticated admin/manager/approver
 * of ANY company could list/view/read the full decision history of EVERY
 * other company's pending approval requests via
 * ApprovalRequestController::index()/show()/history(). Fixed by adding a
 * real, additive company_id column (populated from the requester's own
 * company at creation time going forward) and backfilling existing rows
 * from `requested_by`'s users.company_id — the same backfill-on-migrate
 * pattern already used for core_audit_logs' company_id (Chantier 8.5-light).
 *
 * validation_approval_hierarchies already HAD a company_id column (added in
 * 2026_08_14_000002) — real design intent to scope some hierarchies per
 * company (ApprovalHierarchyService::getHierarchyByCompany() already reads
 * it) — but it was never enforced anywhere: ApprovalHierarchyController's
 * index()/show()/update()/destroy() applied zero filter/ownership check, and
 * store() accepted company_id as a fully client-controlled value. No schema
 * change needed there — just backfilling the column doesn't apply, since a
 * NULL company_id is (and remains) the deliberate "global/shared config"
 * marker used by Achats' ApprovalRoutingService::createDefaultWorkflows().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_approval_requests') && ! Schema::hasColumn('validation_approval_requests', 'company_id')) {
            Schema::table('validation_approval_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('requested_by');
            });

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                DB::statement(
                    'UPDATE validation_approval_requests
                     SET company_id = (SELECT company_id FROM users WHERE users.id = validation_approval_requests.requested_by)
                     WHERE company_id IS NULL'
                );
            }
        }
    }

    public function down(): void
    {
        // Additive, guard-checked column — no destructive rollback.
    }
};
