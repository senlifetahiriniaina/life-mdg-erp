<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\HR\Models\LeaveApprovalLog` has pointed at this table since its
 * creation, but no migration ever created it — every write would have
 * fataled with "table not found". `HRService::approveLeave()`/`rejectLeave()`
 * never wrote to it either, so the gap stayed invisible until now.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_leave_approval_log')) {
            Schema::create('hr_leave_approval_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('leave_request_id')
                    ->constrained('hr_leave_requests')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('level')->default(1);
                $table->foreignId('approver_id')->nullable()
                    ->references('id')->on('hr_employees')->nullOnDelete();
                $table->string('approver_role')->nullable();
                $table->enum('action', ['approved', 'rejected']);
                $table->text('comment')->nullable();
                $table->timestamp('actioned_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['leave_request_id', 'level']);
            });
        }

        if (Schema::hasTable('hr_leave_types')) {
            Schema::table('hr_leave_types', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_leave_types', 'approval_levels')) {
                    // Drives the simple submitted → manager → HR stepper shown
                    // on the leave request detail screen — not routed through
                    // the full Validation engine (deliberately out of scope,
                    // see plan).
                    $table->unsignedTinyInteger('approval_levels')->default(1);
                }

                // LeaveType's fillable/factory/resource/scopeActive() have
                // referenced a `status` string column since this table was
                // extracted from WideHalo — only the unrelated `is_active`
                // boolean was ever actually migrated, so every factory-backed
                // LeaveType insert (and everything downstream of it) has been
                // silently fataling since day one.
                if (! Schema::hasColumn('hr_leave_types', 'status')) {
                    $table->string('status')->default('active');
                }
            });
        }

        // Same story on hr_leave_requests: LeaveRequest's fillable and
        // HRService::approveLeave()/rejectLeave() have written to
        // `approval_notes` since they existed — the column was never
        // migrated, so every approve/reject call has fatalled with
        // "no such column: approval_notes" in production.
        if (Schema::hasTable('hr_leave_requests') && ! Schema::hasColumn('hr_leave_requests', 'approval_notes')) {
            Schema::table('hr_leave_requests', function (Blueprint $table) {
                $table->text('approval_notes')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_approval_log');

        if (Schema::hasTable('hr_leave_types')) {
            Schema::table('hr_leave_types', function (Blueprint $table) {
                if (Schema::hasColumn('hr_leave_types', 'approval_levels')) {
                    $table->dropColumn('approval_levels');
                }
                if (Schema::hasColumn('hr_leave_types', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        if (Schema::hasTable('hr_leave_requests') && Schema::hasColumn('hr_leave_requests', 'approval_notes')) {
            Schema::table('hr_leave_requests', function (Blueprint $table) {
                $table->dropColumn('approval_notes');
            });
        }
    }
};
