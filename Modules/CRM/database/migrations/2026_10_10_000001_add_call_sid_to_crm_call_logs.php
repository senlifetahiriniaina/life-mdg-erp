<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 38.3 (CRM 14-layer re-audit, layer 4/10): `VoipService::initiateCall()` has always
 * fetched a real Twilio `call_sid` from the outbound-call API response and returned it to the
 * caller, but never persisted it anywhere — `crm_call_logs` never had a `call_sid` column at
 * all (confirmed via `Schema::getColumnListing()`). Two real, live consequences confirmed
 * before this fix:
 *   - `VoipService::startRecording()`'s `$callLog->call_sid ?? null` guard was permanently
 *     null, so the real Twilio "start recording" API call it gates was dead code — recording
 *     has never actually started for any real call, silently.
 *   - The real, already-existing `resources/js/Components/CRM/ClickToCallButton.vue` polls
 *     `GET .../voip/status?call_sid=` — a route that never existed at all (see
 *     VoipController::status(), added alongside this migration) and, even once routed, would
 *     have had nothing real to look up a CallLog by.
 * Nullable/unique (a call not yet correlated to a Twilio SID, or a non-Twilio manual log
 * entry, is legitimate) and indexed for the new status() lookup's WHERE clause.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_call_logs') || Schema::hasColumn('crm_call_logs', 'call_sid')) {
            return;
        }

        Schema::table('crm_call_logs', function (Blueprint $table): void {
            $table->string('call_sid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_call_logs') && Schema::hasColumn('crm_call_logs', 'call_sid')) {
            Schema::table('crm_call_logs', function (Blueprint $table): void {
                $table->dropUnique(['call_sid']);
                $table->dropColumn('call_sid');
            });
        }
    }
};
