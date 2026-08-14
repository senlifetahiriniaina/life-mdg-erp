<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the columns ApprovalRoutingResolver needs to drive/record multi-level,
// role-aware, leave/working-hours-aware routing. current_level/total_levels
// already exist (added by 2026_05_29_000005_patch_stub_table_schemas.php) but
// nothing writes to them yet — this migration doesn't touch those two.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_approval_requests')) {
            Schema::table('validation_approval_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_requests', 'hierarchy_id')) {
                    $table->unsignedBigInteger('hierarchy_id')->nullable()->index();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'escalated_from_id')) {
                    $table->unsignedBigInteger('escalated_from_id')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_requests', 'escalation_reason')) {
                    $table->string('escalation_reason', 32)->nullable();
                }
            });
        }
    }

    public function down(): void {}
};
