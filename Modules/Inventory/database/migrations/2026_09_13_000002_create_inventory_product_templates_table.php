<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 17 — catalogue de templates de produits pour le domaine
 * vestimentaire (matières premières, accessoires, vêtements semi-finis,
 * produits finis), pour accélérer la création de produits : un template
 * pré-remplit catégorie/unité/attributs, et sa catégorie porte le routage
 * comptable suggéré (voir 2026_09_13_000001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_product_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('family', 30);
            $table->foreignId('category_id')->constrained('inventory_categories');
            $table->foreignId('unit_id')->nullable()->constrained('inventory_units')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('default_attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_product_templates');
    }
};
