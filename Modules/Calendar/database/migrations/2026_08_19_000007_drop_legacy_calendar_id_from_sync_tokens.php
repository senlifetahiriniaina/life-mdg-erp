<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * calendar_sync_tokens was originally designed around one row per
 * (calendar_id, provider) — a single-calendar sync token, NOT NULL FK to
 * calendar_calendars. The 2026_08_17_000001 patch redesigned the table
 * around one row per (user_id, provider), tracking every synced calendar
 * in the new calendar_ids JSON column — but never dropped the old
 * calendar_id column, so it stayed NOT NULL and every insert that follows
 * the model's real $fillable (which has never included calendar_id) fails.
 * The model, its factory and every real caller already agree on the
 * user_id-based design; this migration finishes that half-done rename.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('calendar_sync_tokens') || ! Schema::hasColumn('calendar_sync_tokens', 'calendar_id')) {
            return;
        }

        Schema::table('calendar_sync_tokens', function (Blueprint $table) {
            $table->dropUnique(['calendar_id', 'provider']);
            $table->dropForeign(['calendar_id']);
        });

        Schema::table('calendar_sync_tokens', function (Blueprint $table) {
            $table->dropColumn('calendar_id');
        });

        if (Schema::hasColumn('calendar_sync_tokens', 'user_id') && ! Schema::hasIndex('calendar_sync_tokens', 'calendar_sync_tokens_user_id_provider_unique')) {
            Schema::table('calendar_sync_tokens', function (Blueprint $table) {
                $table->unique(['user_id', 'provider']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('calendar_sync_tokens') || Schema::hasColumn('calendar_sync_tokens', 'calendar_id')) {
            return;
        }

        if (Schema::hasIndex('calendar_sync_tokens', 'calendar_sync_tokens_user_id_provider_unique')) {
            Schema::table('calendar_sync_tokens', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'provider']);
            });
        }

        Schema::table('calendar_sync_tokens', function (Blueprint $table) {
            $table->foreignId('calendar_id')->nullable()->after('id')->constrained('calendar_calendars')->cascadeOnDelete();
        });
    }
};
