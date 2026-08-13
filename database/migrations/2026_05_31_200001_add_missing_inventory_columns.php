<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // inventory_valuation_runs — missing product_count, results, created_by, name, method, status, total_value
        if (Schema::hasTable('inventory_valuation_runs')) {
            Schema::table('inventory_valuation_runs', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_valuation_runs', 'product_count')) {
                    $table->integer('product_count')->default(0);
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'results')) {
                    $table->json('results')->nullable();
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'name')) {
                    $table->string('name')->nullable();
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'method')) {
                    $table->string('method', 20)->nullable();
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'status')) {
                    $table->string('status', 20)->default('draft');
                }
                if (!Schema::hasColumn('inventory_valuation_runs', 'total_value')) {
                    $table->decimal('total_value', 15, 2)->default(0);
                }
            });
        }

        // inventory_transfer_orders — missing received_at, approved_at + extra columns
        if (Schema::hasTable('inventory_transfer_orders')) {
            Schema::table('inventory_transfer_orders', function (Blueprint $table) {
                foreach ([
                    'status', 'type', 'priority',
                    'expected_delivery_date', 'received_at', 'approved_at', 'shipped_at',
                    'total_items', 'total_value',
                ] as $col) {
                    if (!Schema::hasColumn('inventory_transfer_orders', $col)) {
                        match ($col) {
                            'status', 'type', 'priority' => $table->string($col, 30)->nullable(),
                            'expected_delivery_date',
                            'received_at',
                            'approved_at',
                            'shipped_at'                  => $table->timestamp($col)->nullable(),
                            'total_items'                 => $table->integer('total_items')->default(0),
                            'total_value'                 => $table->decimal('total_value', 15, 2)->default(0),
                        };
                    }
                }
            });
        }

        // inventory_transfer_order_lines — missing requested_quantity + others
        if (Schema::hasTable('inventory_transfer_order_lines')) {
            Schema::table('inventory_transfer_order_lines', function (Blueprint $table) {
                foreach ([
                    'transfer_order_id', 'product_id',
                    'requested_quantity', 'approved_quantity',
                    'shipped_quantity', 'received_quantity', 'unit_cost',
                ] as $col) {
                    if (!Schema::hasColumn('inventory_transfer_order_lines', $col)) {
                        match ($col) {
                            'transfer_order_id', 'product_id' => $table->unsignedBigInteger($col)->nullable(),
                            'approved_quantity'               => $table->decimal($col, 15, 4)->nullable(),
                            default                           => $table->decimal($col, 15, 4)->default(0),
                        };
                    }
                }
                if (!Schema::hasColumn('inventory_transfer_order_lines', 'notes')) {
                    $table->text('notes')->nullable();
                }
            });
        }

        // inventory_redistribution_rules — missing from_warehouse_id + others
        if (Schema::hasTable('inventory_redistribution_rules')) {
            Schema::table('inventory_redistribution_rules', function (Blueprint $table) {
                foreach ([
                    'name', 'rule_type',
                    'from_warehouse_id', 'to_warehouse_id', 'product_id',
                    'trigger_threshold', 'transfer_quantity',
                    'is_active', 'priority',
                ] as $col) {
                    if (!Schema::hasColumn('inventory_redistribution_rules', $col)) {
                        match ($col) {
                            'name', 'rule_type'                => $table->string($col)->nullable(),
                            'from_warehouse_id',
                            'to_warehouse_id',
                            'product_id'                       => $table->unsignedBigInteger($col)->nullable(),
                            'trigger_threshold',
                            'transfer_quantity'                => $table->decimal($col, 15, 4)->default(0),
                            'is_active'                        => $table->boolean($col)->default(true),
                            'priority'                         => $table->integer($col)->default(0),
                        };
                    }
                }
            });
        }

        // inventory_picking_orders — missing started_at + assigned_to
        if (Schema::hasTable('inventory_picking_orders')) {
            Schema::table('inventory_picking_orders', function (Blueprint $table) {
                foreach (['assigned_to', 'started_at', 'completed_at'] as $col) {
                    if (!Schema::hasColumn('inventory_picking_orders', $col)) {
                        match ($col) {
                            'assigned_to' => $table->unsignedBigInteger($col)->nullable(),
                            default       => $table->timestamp($col)->nullable(),
                        };
                    }
                }
            });
        }

        // inventory_pick_lines — missing wave_id + others
        if (Schema::hasTable('inventory_pick_lines')) {
            Schema::table('inventory_pick_lines', function (Blueprint $table) {
                foreach ([
                    'wave_id', 'product_id',
                    'warehouse_location',
                    'qty_requested', 'qty_picked',
                    'status',
                ] as $col) {
                    if (!Schema::hasColumn('inventory_pick_lines', $col)) {
                        match ($col) {
                            'wave_id', 'product_id'  => $table->unsignedBigInteger($col)->nullable(),
                            'warehouse_location',
                            'status'                 => $table->string($col)->nullable(),
                            default                  => $table->decimal($col, 15, 4)->default(0),
                        };
                    }
                }
            });
        }

        // inventory_purchase_orders — missing sent_at
        if (Schema::hasTable('inventory_purchase_orders')) {
            Schema::table('inventory_purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('inventory_purchase_orders', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable();
                }
            });
        }

        // inventory_purchase_order_items — missing product_name, sku, etc.
        if (Schema::hasTable('inventory_purchase_order_items')) {
            Schema::table('inventory_purchase_order_items', function (Blueprint $table) {
                foreach ([
                    'product_name', 'sku',
                    'quantity_ordered', 'unit_price', 'total_price', 'quantity_received',
                ] as $col) {
                    if (!Schema::hasColumn('inventory_purchase_order_items', $col)) {
                        match ($col) {
                            'product_name', 'sku' => $table->string($col)->nullable(),
                            default               => $table->decimal($col, 15, 4)->default(0),
                        };
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // intentionally left empty — column drops are destructive
    }
};
