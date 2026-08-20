<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 23 (volet C de la feuille de route Chantier 21) — commande de
 * production simplifiée : suivi du statut d'un article entre "matières
 * réunies" (Achats) et "livré", en passant par la sous-traitance de
 * production réelle de Life MDG. Délibérément pas un moteur MRP/BOM/gamme
 * (Manufacturing est hors périmètre de cette extraction) — juste un
 * pipeline de statuts simple, sur le même principe que CostingSheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('costing_sheet_id')->nullable()->constrained('inventory_costing_sheets')->nullOnDelete();
            // Lien souple vers Sales (pas de contrainte FK), même précédent que
            // CostingSheet.opportunity_id — évite de coupler Inventory à Sales.
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreignId('subcontractor_supplier_id')->nullable()->constrained('achats_suppliers')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 30)->default('draft');
            $table->date('started_at')->nullable();
            $table->date('expected_delivery_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sales_order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_production_orders');
    }
};
