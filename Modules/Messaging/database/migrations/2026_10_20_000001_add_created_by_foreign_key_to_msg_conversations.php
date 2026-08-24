<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.28 (14-layer deep audit of Modules\Messaging) — relational
 * layer (10): `msg_conversations.created_by` was a bare `unsignedBigInteger`
 * with no foreign key, unlike its siblings `user_id`/`sender_id` (both
 * `foreignId(...)->constrained('users')->cascadeOnDelete()`) and unlike the
 * established `created_by` convention used elsewhere in this session for a
 * "who created this" audit column (e.g. Inventory's `CostingSheet`/
 * `ProductionOrder`: `foreignId('created_by')->nullable()->constrained('users')
 * ->nullOnDelete()`). `nullOnDelete()` rather than `cascadeOnDelete()` here —
 * unlike a message/participant row (real conversation content, legitimately
 * purged with its author under GDPR erasure), a conversation itself should
 * outlive its creator being purged; it keeps existing for the remaining
 * participants, just with an unset `created_by`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('msg_conversations') || ! Schema::hasColumn('msg_conversations', 'created_by')) {
            return;
        }

        // Any pre-existing row whose created_by no longer resolves to a real
        // user would violate the new constraint — normalize to NULL first
        // (matching this app's established backfill-before-constrain pattern).
        \Illuminate\Support\Facades\DB::statement(
            'UPDATE msg_conversations SET created_by = NULL WHERE created_by IS NOT NULL AND created_by NOT IN (SELECT id FROM users)'
        );

        Schema::table('msg_conversations', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('msg_conversations') || ! Schema::hasColumn('msg_conversations', 'created_by')) {
            return;
        }

        Schema::table('msg_conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
    }
};
