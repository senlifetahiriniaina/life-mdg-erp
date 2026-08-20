<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 25 (volet E de la feuille de route Chantier 21) — commandes
 * récurrentes : un client répétitif (le cas courant en confection/textile
 * — le même article, régulièrement) n'a pas besoin de ressaisir sa
 * commande à chaque fois. Un modèle réutilisable génère une vraie
 * SalesOrder (via le vrai SalesService::createOrder(), pas un chemin de
 * données parallèle) à échéance régulière.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_recurring_order_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('reference')->unique();
            $table->string('name');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('currency', 3)->default('MGA');
            $table->string('recurrence', 20); // weekly | monthly | quarterly
            $table->date('next_run_at');
            $table->date('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active', 'next_run_at']);
        });

        Schema::create('sales_recurring_order_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_order_template_id')->constrained('sales_recurring_order_templates')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('discount_percent', 6, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_recurring_order_template_lines');
        Schema::dropIfExists('sales_recurring_order_templates');
    }
};
