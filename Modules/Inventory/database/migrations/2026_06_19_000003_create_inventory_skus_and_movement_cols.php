<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_skus')) {
            Schema::create('inventory_skus', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('unit')->nullable();
                $table->decimal('reorder_level', 15, 2)->default(0);
                $table->decimal('reorder_point', 15, 2)->default(0);
                $table->decimal('reorder_qty', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // The SKU stock subsystem records movements in the existing movements table.
        if (Schema::hasTable('inventory_stock_movements')) {
            Schema::table('inventory_stock_movements', function (Blueprint $table) {
                if (! Schema::hasColumn('inventory_stock_movements', 'sku_id')) {
                    $table->unsignedBigInteger('sku_id')->nullable()->index()->after('id');
                }
                if (! Schema::hasColumn('inventory_stock_movements', 'reference')) {
                    $table->string('reference')->nullable()->after('reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_stock_movements')) {
            Schema::table('inventory_stock_movements', function (Blueprint $table) {
                foreach (['sku_id', 'reference'] as $col) {
                    if (Schema::hasColumn('inventory_stock_movements', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('inventory_skus');
    }
};
