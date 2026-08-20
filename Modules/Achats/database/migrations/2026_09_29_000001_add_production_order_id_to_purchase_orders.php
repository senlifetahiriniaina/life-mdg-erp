<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 24 (volet D de la feuille de route Chantier 21) — traçabilité
 * bout-en-bout. Lien souple (pas de contrainte FK) vers
 * inventory_production_orders, marquant qu'un achat de matières a été
 * réalisé pour une commande de production donnée — même précédent que
 * CostingSheet.opportunity_id/ProductionOrder.sales_order_id : évite de
 * coupler Achats à Inventory par une contrainte FK stricte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('production_order_id')->nullable()->after('company_id');
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['production_order_id']);
            $table->dropColumn('production_order_id');
        });
    }
};
