<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.17 (HR deep 14-layer audit) — schema fixes found by empirical
 * execution, not code review alone:
 *
 * 1. `hr_attendance.notes` — Attendance::$fillable declared it, the real
 *    admin "Mark Attendance" form (Attendance/Manage.vue) collects it, but
 *    the column never existed on the catch-all-scaffolded table — every
 *    note was silently dropped on write. Added.
 *
 * 2. `company_id` on `hr_employees`/`hr_departments`/`hr_job_positions` —
 *    closes the cross-tenant data leak already flagged twice in this
 *    session and left unfixed (Chantier 19 Lot 2: "HR has zero
 *    company-based tenant isolation anywhere ... confirmed empirically that
 *    a Company A hr-manager sees every company's employees"; re-confirmed
 *    here via `Schema::getColumnListing()` before writing this migration).
 *    Nullable/indexed, matching the established, already-proven-safe
 *    pattern from Projects (Chantier 10) and CRM (Chantier 19 Lot 1) — a
 *    no-op for pre-chantier data or a not-yet-provisioned user, real
 *    scoping once both sides carry a real value.
 *
 * 3. Drop `hr_positions` — confirmed dead (Layer 9): zero controller/route
 *    anywhere references `Modules\HR\Models\Position` (only the real,
 *    different `JobPosition` is routed), zero real write path populates it
 *    (not even DemoSeeder — already documented at Chantier 19 Lot 2's own
 *    HrDashboardService fix), and `HRService::createPosition()`/
 *    `updatePosition()`/`getPositionsByDepartment()` — the model's only
 *    would-be callers — have zero controller consumers either, confirmed
 *    via grep. `Department::positions()`/the model/its factory/
 *    `PositionResource` (which was in practice only ever applied to the
 *    real, different `JobPosition` model, not `Position` — see
 *    EmployeeResource) are removed in the same chantier.
 *
 * 4. Drop `hr_leave_balances` — confirmed dead (Layer 9): `LeaveBalance`
 *    has zero controller/service consumer anywhere (confirmed via grep),
 *    and is a redundant duplicate of the already-real, already-working
 *    computed-balance approach every real leave-balance endpoint in this
 *    module already uses (`EmployeeSelfServiceController::leaveBalance()`,
 *    `EmployeePortalController::leaveBalance()`,
 *    `HRService::getEmployeeLeaveBalance()` — all compute
 *    days_per_year-minus-days_taken on the fly, never read/write this
 *    table). The model + its factory are removed in the same chantier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employees', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            }
        });

        Schema::table('hr_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_departments', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            }
        });

        Schema::table('hr_job_positions', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_job_positions', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            }
        });

        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_leave_balances');
    }

    public function down(): void
    {
        // Deliberately not reversible for the two dropped tables (confirmed
        // dead, zero real data ever written to either) — matching this
        // session's established precedent for dead-scaffold-table drops
        // (see Chantier 9). The additive columns above are safe to leave in
        // place on rollback.
    }
};
