<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3: DeliveryRoute/RouteStop/CarrierRateCard are real, fully
 * written models (with HasAuditLog, real relations, real business logic)
 * already used by live, routed code — RouteOptimizationService (behind
 * CustomsRouteController's routes/optimize|start|complete and
 * routeStopComplete) and CarrierIntegrationService::getRate() (behind
 * GET carriers/{id}/rate) — but none of their tables (lgx_delivery_routes,
 * lgx_route_stops, lgx_carrier_rate_cards) were ever migrated, so every
 * one of those endpoints crashed outright with a "table not found" error.
 * Column shape matches each model's $fillable/$casts exactly; follows the
 * same id()/company_id (not tenant_id) convention as the sibling
 * lgx_vehicles table (2026_08_18_000001) rather than the generic
 * tenant_id/status/data scaffold pattern used elsewhere in this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lgx_delivery_routes')) {
            Schema::create('lgx_delivery_routes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('reference')->nullable();
                $table->string('name')->nullable();
                $table->date('date')->nullable();
                $table->string('status')->default('planned');
                $table->unsignedBigInteger('vehicle_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->decimal('total_distance_km', 10, 2)->nullable();
                $table->unsignedInteger('total_duration_min')->nullable();
                $table->unsignedInteger('total_stops')->nullable();
                $table->boolean('optimized')->default(false);
                $table->timestamps();

                $table->index('company_id');
            });
        }

        if (!Schema::hasTable('lgx_route_stops')) {
            Schema::create('lgx_route_stops', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('route_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->unsignedInteger('sequence')->default(0);
                $table->string('type')->nullable();
                $table->string('address')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->string('planned_arrival')->nullable();
                $table->timestamp('actual_arrival')->nullable();
                $table->unsignedInteger('planned_duration_min')->nullable();
                $table->string('status')->default('pending');
                $table->text('proof_of_delivery')->nullable();
                $table->string('signature_url')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('route_id');
                $table->foreign('route_id')->references('id')->on('lgx_delivery_routes')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('lgx_carrier_rate_cards')) {
            Schema::create('lgx_carrier_rate_cards', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('carrier_id');
                $table->string('origin_country', 2)->nullable();
                $table->string('dest_country', 2)->nullable();
                $table->string('service_type')->nullable();
                $table->decimal('weight_min_kg', 10, 3)->nullable();
                $table->decimal('weight_max_kg', 10, 3)->nullable();
                $table->decimal('base_rate', 12, 2)->nullable();
                $table->decimal('per_kg_rate', 12, 4)->nullable();
                $table->string('currency', 3)->nullable();
                $table->unsignedInteger('transit_days_min')->nullable();
                $table->unsignedInteger('transit_days_max')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('carrier_id');
                $table->foreign('carrier_id')->references('id')->on('logistics_carriers')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_carrier_rate_cards');
        Schema::dropIfExists('lgx_route_stops');
        Schema::dropIfExists('lgx_delivery_routes');
    }
};
