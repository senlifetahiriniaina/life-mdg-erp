<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Modules\HR\Models\LeaveRequest uses the SoftDeletes trait (and always has),
// but hr_leave_requests was never given a deleted_at column — every query
// through the model silently adds a whereNull('deleted_at') global scope,
// which fails with "no such column" the moment any real query runs. Surfaced
// while adding LeaveRequest::scopeApprovedAndCoveringDate() for the approval
// engine's leave-aware routing.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_leave_requests') && ! Schema::hasColumn('hr_leave_requests', 'deleted_at')) {
            Schema::table('hr_leave_requests', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_leave_requests') && Schema::hasColumn('hr_leave_requests', 'deleted_at')) {
            Schema::table('hr_leave_requests', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
