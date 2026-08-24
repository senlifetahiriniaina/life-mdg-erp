<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32 — HR module had **zero** company-based tenant isolation
 * anywhere at all — not even the phantom `users.tenant_id ?? default-bucket`
 * pattern already fixed repeatedly elsewhere in this app, just a complete
 * absence of any real per-record `company_id` column on any of the core HR
 * tables. Confirmed empirically (and already locked in as a standing
 * regression fixture, see `Chantier19HRReauditTest.php`) that a Company A
 * hr-manager could list/view/edit/delete Company B's employees, departments,
 * leave requests, and every other HR resource.
 *
 * Adds a real, populated `company_id` (matching the `App\Models\Company`
 * boundary column used throughout the rest of the app — never the phantom
 * `users.tenant_id` column) to the 8 HR tables that need one. Nullable/
 * indexed, additive only — no backfill, since none of these tables ever had
 * ANY tenant column before (unlike Achats' pre-existing dead `tenant_id`
 * scaffold columns), so there is nothing to migrate data from.
 *
 * `hr_employee_skills` (EmployeeSkill) is deliberately excluded — it's a
 * thin pivot always queried through its `employee_id` (already scoped via
 * the parent Employee's own company_id, e.g.
 * SkillController::employeeSkills()/addEmployeeSkill()), so it has no
 * standalone consumer that would need its own tenant column.
 */
return new class extends Migration
{
    private const TABLES = [
        'hr_departments',
        'hr_job_positions',
        'hr_employees',
        'hr_leave_types',
        'hr_leave_requests',
        'hr_salary_bands',
        'hr_skills',
        'hr_attendance_records',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
