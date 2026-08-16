<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('logistics_tracking_events')) {
            Schema::table('logistics_tracking_events', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_tracking_events', 'provider_event_id')) {
                    $table->string('provider_event_id', 191)->nullable()->after('recorded_by');
                    $table->index('provider_event_id');
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'idempotency_key')) {
                    $table->string('idempotency_key', 191)->nullable()->after('provider_event_id');
                    $table->index('idempotency_key');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('logistics_tracking_events')) {
            Schema::table('logistics_tracking_events', function (Blueprint $table) {
                if (Schema::hasColumn('logistics_tracking_events', 'provider_event_id')) {
                    $table->dropIndex('logistics_tracking_events_provider_event_id_index');
                    $table->dropColumn('provider_event_id');
                }
                if (Schema::hasColumn('logistics_tracking_events', 'idempotency_key')) {
                    $table->dropIndex('logistics_tracking_events_idempotency_key_index');
                    $table->dropColumn('idempotency_key');
                }
            });
        }
    }
};
