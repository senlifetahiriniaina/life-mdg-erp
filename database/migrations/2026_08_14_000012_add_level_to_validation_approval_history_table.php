<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * validation_approval_history recorded action/old_status/new_status/
 * changed_by/changed_at but never which hierarchy level a decision was
 * made at — impossible to attribute a history row to a specific step for
 * a per-step approval-panel UI. Additive, guard-checked per this repo's
 * established migration convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_approval_history') && ! Schema::hasColumn('validation_approval_history', 'level')) {
            Schema::table('validation_approval_history', function (Blueprint $table): void {
                $table->unsignedTinyInteger('level')->nullable()->after('request_id');
            });
        }
    }

    public function down(): void
    {
        // Additive, guard-checked column — no destructive rollback.
    }
};
