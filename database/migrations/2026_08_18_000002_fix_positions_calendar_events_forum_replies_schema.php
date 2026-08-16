<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three unrelated schema/model drifts, grouped as Chantier 4 D6:
 *
 * 1. hr_positions was created by the generic stub loop in
 *    2026_05_29_000003_create_all_missing_module_tables.php
 *    (id/tenant_id/status/data only), but Modules\HR\Models\Position
 *    fills title/description/department_id/level/salary_min/salary_max/
 *    headcount/status. Add the real columns additively.
 *
 * 2. calendar_events.tenant_id is NOT NULL (string 36), but
 *    CalendarService::createEvent() passes $data['tenant_id'] ?? null and
 *    nothing upstream supplies one -- every event insert fails. Same
 *    nullable-intended drift already fixed for calendar_calendars.tenant_id
 *    in Chantier 3 C2.
 *
 * 3. helpdesk_forum_replies serves two forum implementations: the
 *    thread-based one (thread_id NOT NULL, is_accepted_answer) and the
 *    post-based one (ForumController + ForumPost::replies() writing
 *    post_id/is_accepted -- neither column exists, and thread_id NOT NULL
 *    blocks post-flow inserts that have no thread). Add post_id/
 *    is_accepted, relax thread_id to nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_positions')) {
            Schema::table('hr_positions', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_positions', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('hr_positions', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('hr_positions', 'department_id')) {
                    $table->unsignedBigInteger('department_id')->nullable()->index();
                }
                if (! Schema::hasColumn('hr_positions', 'level')) {
                    $table->string('level', 50)->nullable();
                }
                if (! Schema::hasColumn('hr_positions', 'salary_min')) {
                    $table->decimal('salary_min', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('hr_positions', 'salary_max')) {
                    $table->decimal('salary_max', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('hr_positions', 'headcount')) {
                    $table->unsignedInteger('headcount')->nullable();
                }
            });
        }

        if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'tenant_id')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->string('tenant_id', 36)->nullable()->change();
            });
        }

        if (Schema::hasTable('helpdesk_forum_replies')) {
            Schema::table('helpdesk_forum_replies', function (Blueprint $table) {
                if (! Schema::hasColumn('helpdesk_forum_replies', 'post_id')) {
                    $table->unsignedBigInteger('post_id')->nullable()->index();
                }
                if (! Schema::hasColumn('helpdesk_forum_replies', 'is_accepted')) {
                    $table->boolean('is_accepted')->default(false);
                }
            });
            Schema::table('helpdesk_forum_replies', function (Blueprint $table) {
                $table->unsignedBigInteger('thread_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_positions')) {
            Schema::table('hr_positions', function (Blueprint $table) {
                foreach (['title', 'description', 'department_id', 'level', 'salary_min', 'salary_max', 'headcount'] as $col) {
                    if (Schema::hasColumn('hr_positions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('helpdesk_forum_replies')) {
            Schema::table('helpdesk_forum_replies', function (Blueprint $table) {
                foreach (['post_id', 'is_accepted'] as $col) {
                    if (Schema::hasColumn('helpdesk_forum_replies', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
