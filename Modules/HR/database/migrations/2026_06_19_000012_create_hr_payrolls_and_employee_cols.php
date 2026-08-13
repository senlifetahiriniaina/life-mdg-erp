<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_employees', 'termination_reason')) {
                    $table->string('termination_reason')->nullable();
                }
                if (! Schema::hasColumn('hr_employees', 'sick_leave_balance')) {
                    $table->decimal('sick_leave_balance', 8, 2)->nullable();
                }
                if (! Schema::hasColumn('hr_employees', 'job_title')) {
                    $table->string('job_title')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_employees')) {
            Schema::table('hr_employees', function (Blueprint $table) {
                foreach (['termination_reason', 'sick_leave_balance'] as $col) {
                    if (Schema::hasColumn('hr_employees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
