<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3 (HR): EmployeeSkill's $fillable/$casts declare expires_at, and
 * SkillController::addEmployeeSkill() validates and writes it, but the
 * catch-all hr_employee_skills patch (2026_05_29_000012) only ever added
 * employee_id/skill_id/level/certified_at — expires_at never existed,
 * a QueryException waiting to happen on the first real write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_skills', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employee_skills', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('certified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hr_employee_skills', function (Blueprint $table) {
            $table->dropColumnIfExists('expires_at');
        });
    }
};
