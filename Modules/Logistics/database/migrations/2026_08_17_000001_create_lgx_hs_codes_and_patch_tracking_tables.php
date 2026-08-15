<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('lgx_hs_codes')) {
            Schema::create('lgx_hs_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->index();
                $table->string('description_fr', 255)->nullable();
                $table->string('description_en', 255)->nullable();
                $table->decimal('duty_rate_default', 5, 2)->nullable();
                $table->boolean('vat_applicable')->default(false);
                $table->boolean('requires_license')->default(false);
                $table->text('notes')->nullable();
                $table->string('chapter', 4)->nullable()->index();
                $table->string('section', 10)->nullable();
                $table->string('unit', 20)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('logistics_delivery_rounds')) {
            Schema::table('logistics_delivery_rounds', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_delivery_rounds', 'started_at')) {
                    $table->timestamp('started_at')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_rounds', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_rounds', 'total_stops')) {
                    $table->unsignedInteger('total_stops')->default(0);
                }
                if (! Schema::hasColumn('logistics_delivery_rounds', 'total_distance_km')) {
                    $table->decimal('total_distance_km', 8, 2)->nullable();
                }
                if (! Schema::hasColumn('logistics_delivery_rounds', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('logistics_tracking_events')) {
            Schema::table('logistics_tracking_events', function (Blueprint $table) {
                if (! Schema::hasColumn('logistics_tracking_events', 'location_city')) {
                    $table->string('location_city', 128)->nullable();
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'location_country')) {
                    $table->string('location_country', 128)->nullable();
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable();
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'carrier_ref')) {
                    $table->string('carrier_ref', 128)->nullable();
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'is_exception')) {
                    $table->boolean('is_exception')->default(false);
                }
                if (! Schema::hasColumn('logistics_tracking_events', 'exception_reason')) {
                    $table->text('exception_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_hs_codes');

        if (Schema::hasTable('logistics_delivery_rounds')) {
            Schema::table('logistics_delivery_rounds', function (Blueprint $table) {
                $table->dropColumn(['started_at', 'completed_at', 'total_stops', 'total_distance_km', 'notes']);
            });
        }

        if (Schema::hasTable('logistics_tracking_events')) {
            Schema::table('logistics_tracking_events', function (Blueprint $table) {
                $table->dropColumn([
                    'location_city', 'location_country', 'latitude', 'longitude',
                    'carrier_ref', 'is_exception', 'exception_reason',
                ]);
            });
        }
    }
};
