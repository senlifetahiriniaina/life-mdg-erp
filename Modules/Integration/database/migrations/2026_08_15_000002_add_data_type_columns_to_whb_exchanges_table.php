<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same drift as whb_permissions (2026_08_15_000001): WhbExchange's
 * $fillable (data_type, local_resource_type, local_resource_id,
 * remote_resource_id, error_message, initiated_by, processed_at) was never
 * matched by 2026_06_07_000006, which only created exchange_type/response/
 * sent_at/received_at. Every real write path (WhbPartnerService::sendData()/
 * createLocalInbound(), and the new federation exchange receiver) has
 * always used the newer shape, so it has always failed. Additive only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('whb_exchanges', function (Blueprint $table) {
            if (! Schema::hasColumn('whb_exchanges', 'data_type')) {
                $table->string('data_type', 64)->nullable()->after('direction')->index();
            }
            if (! Schema::hasColumn('whb_exchanges', 'local_resource_type')) {
                $table->string('local_resource_type', 64)->nullable()->after('data_type');
            }
            if (! Schema::hasColumn('whb_exchanges', 'local_resource_id')) {
                $table->unsignedBigInteger('local_resource_id')->nullable()->after('local_resource_type');
            }
            if (! Schema::hasColumn('whb_exchanges', 'remote_resource_id')) {
                $table->string('remote_resource_id', 64)->nullable()->after('local_resource_id');
            }
            if (! Schema::hasColumn('whb_exchanges', 'error_message')) {
                $table->text('error_message')->nullable()->after('response');
            }
            if (! Schema::hasColumn('whb_exchanges', 'initiated_by')) {
                $table->unsignedBigInteger('initiated_by')->nullable()->after('error_message');
            }
            if (! Schema::hasColumn('whb_exchanges', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('received_at');
            }
        });

        // exchange_type was NOT NULL with no default and nothing sets it —
        // same story as whb_permissions.resource_type.
        Schema::table('whb_exchanges', function (Blueprint $table) {
            $table->string('exchange_type', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whb_exchanges', function (Blueprint $table) {
            $table->dropColumn([
                'data_type',
                'local_resource_type',
                'local_resource_id',
                'remote_resource_id',
                'error_message',
                'initiated_by',
                'processed_at',
            ]);
        });
    }
};
