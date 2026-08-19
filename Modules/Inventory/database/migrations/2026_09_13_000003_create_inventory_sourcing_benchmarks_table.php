<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 17 — journal des observations de prix chez des fournisseurs de
 * sourcing externes (Chine/Europe), accumulé au fil de l'utilisation de
 * l'app ("idée sur la structure de coûts au fur et à mesure"), pour
 * comparer le prix catalogue interne au prix constaté ailleurs. Volontairement
 * un journal d'observations manuelles/importées, pas un scraping en direct —
 * voir CLAUDE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sourcing_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('inventory_products')->nullOnDelete();
            $table->foreignId('product_template_id')->nullable()->constrained('inventory_product_templates')->nullOnDelete();
            $table->string('material_label')->nullable();
            $table->string('source', 30);
            $table->string('source_name_other')->nullable();
            $table->string('source_url')->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->string('currency', 3);
            $table->string('unit', 20)->nullable();
            $table->decimal('quantity_reference', 14, 4)->nullable();
            $table->date('observed_at');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'observed_at']);
            $table->index(['product_template_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sourcing_benchmarks');
    }
};
