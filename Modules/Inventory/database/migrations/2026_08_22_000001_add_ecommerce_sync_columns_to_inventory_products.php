<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EcommerceSyncService reads/writes 'ecommerce_synced_at'/'ecommerce_sync_pending'
 * on Product, but the columns were never migrated onto inventory_products and
 * neither field is in Product::$fillable — every "sync" silently no-ops
 * (Eloquent's fill() drops non-fillable keys instead of erroring), so the
 * sync status shown to users was always stale/empty regardless of real syncs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_products', 'ecommerce_synced_at')) {
                $table->timestamp('ecommerce_synced_at')->nullable();
            }
            if (! Schema::hasColumn('inventory_products', 'ecommerce_sync_pending')) {
                $table->boolean('ecommerce_sync_pending')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn(['ecommerce_synced_at', 'ecommerce_sync_pending']);
        });
    }
};
