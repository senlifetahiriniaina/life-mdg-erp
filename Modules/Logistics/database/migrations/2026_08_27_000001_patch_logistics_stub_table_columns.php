<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3: the catch-all scaffold migration (database/migrations/
 * 2026_05_29_000003_create_all_missing_module_tables.php) created 12
 * logistics_* tables as bare id/tenant_id/status/data/timestamps stubs.
 * Most were patched to match their real models by later passes; this
 * closes the remainder. Carrier/CarrierRate/Route/Shipment gaps back
 * live, routed endpoints that crash on write today
 * (CarrierController::store/update, CarrierRateController's zone/
 * validity fields, RouteController's full field set, ShipmentService's
 * booked_at/picked_up_at and LogisticsAnalyticsService's co2_kg query).
 * FreightInvoice/PutawayRule/ShipmentLine/ShipmentPackage were left as
 * fully bare stubs (only the 7 generic scaffold columns) despite each
 * having a real, routed controller and a fully-written model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_carriers', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_carriers', 'website')) {
                $table->string('website')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('logistics_carriers', 'tracking_url_template')) {
                $table->string('tracking_url_template')->nullable()->after('website');
            }
            if (!Schema::hasColumn('logistics_carriers', 'api_provider')) {
                $table->string('api_provider', 50)->nullable()->after('tracking_url_template');
            }
            if (!Schema::hasColumn('logistics_carriers', 'api_credentials')) {
                $table->text('api_credentials')->nullable()->after('api_provider');
            }
            if (!Schema::hasColumn('logistics_carriers', 'notes')) {
                $table->text('notes')->nullable()->after('api_credentials');
            }
        });

        Schema::table('logistics_carrier_rates', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_carrier_rates', 'origin_zone')) {
                $table->string('origin_zone', 100)->nullable()->after('carrier_id');
            }
            if (!Schema::hasColumn('logistics_carrier_rates', 'destination_zone')) {
                $table->string('destination_zone', 100)->nullable()->after('origin_zone');
            }
            if (!Schema::hasColumn('logistics_carrier_rates', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('transit_days');
            }
            if (!Schema::hasColumn('logistics_carrier_rates', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('valid_from');
            }
        });

        Schema::table('logistics_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_routes', 'code')) {
                $table->string('code', 20)->nullable()->after('name');
            }
            if (!Schema::hasColumn('logistics_routes', 'origin_name')) {
                $table->string('origin_name', 200)->nullable()->after('origin');
            }
            if (!Schema::hasColumn('logistics_routes', 'origin_country')) {
                $table->string('origin_country')->nullable()->after('origin_name');
            }
            if (!Schema::hasColumn('logistics_routes', 'origin_address')) {
                $table->string('origin_address')->nullable()->after('origin_country');
            }
            if (!Schema::hasColumn('logistics_routes', 'destination_name')) {
                $table->string('destination_name', 200)->nullable()->after('destination');
            }
            if (!Schema::hasColumn('logistics_routes', 'destination_country')) {
                $table->string('destination_country')->nullable()->after('destination_name');
            }
            if (!Schema::hasColumn('logistics_routes', 'destination_address')) {
                $table->string('destination_address')->nullable()->after('destination_country');
            }
            if (!Schema::hasColumn('logistics_routes', 'mode')) {
                $table->string('mode', 30)->nullable()->after('destination_address');
            }
            if (!Schema::hasColumn('logistics_routes', 'distance_km')) {
                $table->unsignedInteger('distance_km')->nullable()->after('mode');
            }
            if (!Schema::hasColumn('logistics_routes', 'estimated_transit_days')) {
                $table->unsignedInteger('estimated_transit_days')->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('logistics_routes', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });

        Schema::table('logistics_shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_shipments', 'origin_warehouse_id')) {
                $table->unsignedBigInteger('origin_warehouse_id')->nullable()->after('origin_location_id');
            }
            if (!Schema::hasColumn('logistics_shipments', 'destination_warehouse_id')) {
                $table->unsignedBigInteger('destination_warehouse_id')->nullable()->after('destination_location_id');
            }
            if (!Schema::hasColumn('logistics_shipments', 'value_currency')) {
                $table->string('value_currency', 3)->nullable()->after('declared_value');
            }
            if (!Schema::hasColumn('logistics_shipments', 'estimated_cost')) {
                $table->decimal('estimated_cost', 14, 4)->nullable()->after('value_currency');
            }
            if (!Schema::hasColumn('logistics_shipments', 'actual_cost')) {
                $table->decimal('actual_cost', 14, 4)->nullable()->after('estimated_cost');
            }
            if (!Schema::hasColumn('logistics_shipments', 'booked_at')) {
                $table->timestamp('booked_at')->nullable()->after('shipped_at');
            }
            if (!Schema::hasColumn('logistics_shipments', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('booked_at');
            }
            if (!Schema::hasColumn('logistics_shipments', 'temperature_min')) {
                $table->decimal('temperature_min', 6, 2)->nullable()->after('requires_cold_chain');
            }
            if (!Schema::hasColumn('logistics_shipments', 'temperature_max')) {
                $table->decimal('temperature_max', 6, 2)->nullable()->after('temperature_min');
            }
            if (!Schema::hasColumn('logistics_shipments', 'co2_kg')) {
                $table->decimal('co2_kg', 12, 4)->nullable()->after('weight_kg');
            }
        });

        // Fully bare stubs (only the 7 generic scaffold columns) — real,
        // routed controllers exist for all four, none could persist a row.
        Schema::table('logistics_freight_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_freight_invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('tenant_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->unsignedBigInteger('carrier_id')->nullable();
                $table->string('type', 30)->nullable();
                $table->decimal('quoted_amount', 14, 4)->nullable();
                $table->decimal('invoiced_amount', 14, 4)->nullable();
                $table->decimal('variance_amount', 14, 4)->nullable();
                $table->string('currency', 3)->nullable();
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('carrier_invoice_ref')->nullable();
                $table->text('dispute_reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
            }
        });

        // PutawayRuleController writes via raw DB::table(), not the PutawayRule
        // Eloquent model — its own $fillable (product_category_id/strategy/
        // temperature_required) is unused dead code; matched the controller's
        // real, validated field set instead (product_category/carrier_id/
        // transport_mode/requires_cold_chain/has_hazmat/notes).
        Schema::table('logistics_putaway_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_putaway_rules', 'name')) {
                $table->string('name')->nullable()->after('tenant_id');
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_category', 100)->nullable();
                $table->unsignedBigInteger('carrier_id')->nullable();
                $table->string('transport_mode', 20)->nullable();
                $table->boolean('requires_cold_chain')->default(false);
                $table->boolean('has_hazmat')->default(false);
                $table->unsignedInteger('priority')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
            }
        });

        Schema::table('logistics_shipment_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_shipment_lines', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('tenant_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('description')->nullable();
                $table->string('sku')->nullable();
                $table->string('hs_code', 20)->nullable();
                $table->decimal('quantity', 12, 4)->nullable();
                $table->string('unit', 20)->nullable();
                $table->decimal('unit_value', 14, 4)->nullable();
                $table->string('country_of_origin', 2)->nullable();
                $table->string('lot_number')->nullable();
                $table->string('serial_number')->nullable();
                $table->date('expiry_date')->nullable();
            }
        });

        Schema::table('logistics_shipment_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('logistics_shipment_packages', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->after('tenant_id');
                $table->string('package_number')->nullable();
                $table->string('type', 30)->nullable();
                $table->decimal('weight_kg', 10, 3)->nullable();
                $table->decimal('length_cm', 10, 2)->nullable();
                $table->decimal('width_cm', 10, 2)->nullable();
                $table->decimal('height_cm', 10, 2)->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('seal_number')->nullable();
                $table->boolean('is_fragile')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('logistics_carriers', function (Blueprint $table) {
            $table->dropColumn(['website', 'tracking_url_template', 'api_provider', 'api_credentials', 'notes']);
        });
        Schema::table('logistics_carrier_rates', function (Blueprint $table) {
            $table->dropColumn(['origin_zone', 'destination_zone', 'valid_from', 'valid_until']);
        });
        Schema::table('logistics_routes', function (Blueprint $table) {
            $table->dropColumn([
                'code', 'origin_name', 'origin_country', 'origin_address',
                'destination_name', 'destination_country', 'destination_address',
                'mode', 'distance_km', 'estimated_transit_days', 'notes',
            ]);
        });
        Schema::table('logistics_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'origin_warehouse_id', 'destination_warehouse_id', 'value_currency',
                'estimated_cost', 'actual_cost', 'booked_at', 'picked_up_at',
                'temperature_min', 'temperature_max', 'co2_kg',
            ]);
        });
        Schema::table('logistics_freight_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_number', 'shipment_id', 'carrier_id', 'type', 'quoted_amount',
                'invoiced_amount', 'variance_amount', 'currency', 'invoice_date', 'due_date',
                'paid_at', 'carrier_invoice_ref', 'dispute_reason', 'notes', 'approved_by', 'created_by',
            ]);
        });
        Schema::table('logistics_putaway_rules', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'location_id', 'product_id', 'product_category', 'carrier_id',
                'transport_mode', 'requires_cold_chain', 'has_hazmat', 'priority', 'is_active', 'notes',
            ]);
        });
        Schema::table('logistics_shipment_lines', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_id', 'product_id', 'description', 'sku', 'hs_code', 'quantity',
                'unit', 'unit_value', 'country_of_origin', 'lot_number', 'serial_number', 'expiry_date',
            ]);
        });
        Schema::table('logistics_shipment_packages', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_id', 'package_number', 'type', 'weight_kg', 'length_cm',
                'width_cm', 'height_cm', 'tracking_number', 'seal_number', 'is_fragile',
            ]);
        });
    }
};
