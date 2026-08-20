<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 21 — nomenclature de coût (BOM devis chiffré) pour le circuit
 * de vente à la commande (avant-vente → devis chiffré → proforma). Une
 * fiche de chiffrage regroupe matière/accessoires/main-d'œuvre/frais fixes
 * pour reproduire numériquement le calcul déjà fait à la main dans les
 * fichiers Excel de chiffrage (tissu, accessoires montage/finition, coût
 * minute, coefficient de couverture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_costing_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('name');
            $table->foreignId('product_template_id')->nullable()->constrained('inventory_product_templates')->nullOnDelete();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->string('gender', 30)->nullable();
            $table->string('size_range', 50)->nullable();
            $table->string('season', 30)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('base_currency', 3)->default('MGA');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('inventory_costing_sheets')->nullOnDelete();

            $table->decimal('production_minutes', 10, 2)->default(0);
            $table->decimal('minute_cost', 12, 4)->default(0);
            $table->decimal('labor_cost', 14, 4)->default(0);
            $table->decimal('fixed_cost_coefficient', 14, 4)->default(0);
            $table->decimal('washing_cost', 14, 4)->default(0);
            $table->decimal('target_margin_percent', 6, 2)->nullable();

            $table->decimal('total_material_cost', 14, 4)->default(0);
            $table->decimal('total_assembly_cost', 14, 4)->default(0);
            $table->decimal('total_finishing_cost', 14, 4)->default(0);
            $table->decimal('total_value_added_cost', 14, 4)->default(0);
            $table->decimal('total_cost_price', 14, 4)->default(0);
            $table->decimal('suggested_selling_price', 14, 4)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('opportunity_id');
        });

        Schema::create('inventory_costing_sheet_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('costing_sheet_id')->constrained('inventory_costing_sheets')->cascadeOnDelete();
            $table->string('section', 30);
            $table->string('designation');
            $table->foreignId('product_template_id')->nullable()->constrained('inventory_product_templates')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('achats_suppliers')->nullOnDelete();
            $table->decimal('consumption_qty', 12, 4)->default(0);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->string('currency', 3)->default('MGA');
            $table->decimal('customs_freight_percent', 6, 2)->default(0);
            $table->decimal('margin_percent', 6, 2)->nullable();
            $table->decimal('line_total', 14, 4)->default(0);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_costing_sheet_lines');
        Schema::dropIfExists('inventory_costing_sheets');
    }
};
