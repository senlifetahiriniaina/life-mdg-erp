<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32 (Inventory "workflow/fulfillment" half) — the fourth
 * application this session of the same tenant-isolation retrofit already
 * applied to CRM (Chantier 10/19), Achats (Chantier 19 Lot 3), and Projects
 * (Chantier 19 Lot 2). Confirmed via grep that none of `PickingOrder`/
 * `TransferOrder`/`Shipment`/`Rma`/`CycleCount`/`DemandForecast`/
 * `ValuationRun`/`SeasonalFactor`/`CrossdockOperation`/`PurchaseOrder`
 * (Inventory's own, not Achats'/Logistics') carried a real `company_id`
 * column before this — every real table name below was verified against
 * this module's actual migration files (the catch-all scaffold migration,
 * `database/migrations/2026_05_29_000003_create_all_missing_module_tables.php`)
 * rather than guessed from the model class name, since several of this
 * module's models use a `$table` that differs from a naive pluralisation.
 *
 * Also covers `inventory_picking_waves` (PickingWave, behind
 * WavePickingController) and `edi_transactions` (EdiTransaction, behind
 * EdiController) — both real models actually touched by 2 of the 4
 * additional controllers this chantier named as "zero-scoping, not cleanly
 * mapped to one of the 10 named models" — investigated and confirmed to
 * need the same real per-record ownership boundary as the 10 named models,
 * so folded into this same migration rather than a third file.
 *
 * `EcommerceSyncController`/`FulfillmentController` (the other 2 flagged
 * controllers) were investigated and found to have no local per-company
 * persisted record to scope at all: the former only ever reads/writes the
 * sibling-owned `Product` model (Inventory "core" scope, already covered by
 * `2026_10_01_000001_add_company_id_to_inventory_core_tables.php`) plus a
 * fire-and-forget queued job; the latter only ever calls out to external
 * 3PL connector services (ShipBob/ShipMonk/FBA), never persists a local
 * Eloquent record with an id a caller could enumerate. Neither needed a
 * new column here — see `Concerns\ScopesToCompany`'s docblock precedent
 * for why a model with nothing to scope gets no trait/Policy work.
 *
 * Additive, nullable, indexed `company_id` on every top-level table;
 * deliberately excludes every child/line-item table in this half
 * (`inventory_picking_lines`, `inventory_pick_lines`,
 * `inventory_transfer_order_lines`, `inventory_purchase_order_items`,
 * `inventory_cycle_count_lines`, `inventory_po_receipt_lines`,
 * `inventory_po_receipts`, `inventory_shipment_events`) — those resolve
 * their tenant boundary via their parent relation instead
 * (`ScopesToCompany::assertSameCompanyViaParent()`), matching the sibling
 * migration's own precedent for `inventory_costing_sheet_lines`. No
 * backfill: none of these tables ever had a real tenant column before, so
 * a record with `company_id = NULL` is treated as the same "untagged"
 * bucket as a caller with no real `company_id` yet — never auto-denied,
 * matching this app's established null==null convention.
 */
return new class extends Migration
{
    private const TABLES = [
        'inventory_picking_orders',
        'inventory_transfer_orders',
        'inventory_shipments',
        'inventory_rmas',
        'inventory_cycle_counts',
        'inventory_demand_forecasts',
        'inventory_valuation_runs',
        'inventory_seasonal_factors',
        'inventory_crossdock_operations',
        'inventory_purchase_orders',
        'inventory_picking_waves',
        'edi_transactions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('company_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('company_id');
                });
            }
        }
    }
};
