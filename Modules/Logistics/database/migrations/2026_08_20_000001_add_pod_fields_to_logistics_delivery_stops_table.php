<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('logistics_delivery_stops')) {
            Schema::table('logistics_delivery_stops', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_delivery_stops', 'recipient_name')) {
                    $table->string('recipient_name')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'recipient_address')) {
                    $table->text('recipient_address')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'recipient_city')) {
                    $table->string('recipient_city')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'recipient_country')) {
                    $table->string('recipient_country', 5)->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'recipient_phone')) {
                    $table->string('recipient_phone')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'arrived_at')) {
                    $table->timestamp('arrived_at')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'pod_signature')) {
                    $table->text('pod_signature')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'pod_photo_path')) {
                    $table->string('pod_photo_path')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'pod_note')) {
                    $table->text('pod_note')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_stops', 'failure_reason')) {
                    $table->string('failure_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('logistics_delivery_stops')) {
            Schema::table('logistics_delivery_stops', function (Blueprint $table) {
                $table->dropColumn([
                    'recipient_name', 'recipient_address', 'recipient_city', 'recipient_country',
                    'recipient_phone', 'latitude', 'longitude', 'arrived_at', 'completed_at',
                    'pod_signature', 'pod_photo_path', 'pod_note', 'failure_reason',
                ]);
            });
        }
    }
};
