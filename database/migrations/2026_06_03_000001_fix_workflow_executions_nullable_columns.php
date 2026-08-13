<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make trigger_data and context nullable on workflow_executions so tests
 * that don't provide them don't hit a MySQL "no default" error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_executions')) {
            return;
        }

        Schema::table('workflow_executions', function (Blueprint $table) {
            if (Schema::hasColumn('workflow_executions', 'trigger_data')) {
                $table->json('trigger_data')->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('workflow_executions', 'context')) {
                $table->json('context')->nullable()->default(null)->change();
            }
            if (Schema::hasColumn('workflow_executions', 'started_at')) {
                $table->timestamp('started_at')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // intentionally no rollback — don't re-introduce NOT NULL without defaults
    }
};
