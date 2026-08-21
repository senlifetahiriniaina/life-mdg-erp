<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 26 (volet B) — objectifs commerciaux assistés par IA. Un
 * "objectif" est une cible de chiffre d'affaires sur une période, pour un
 * périmètre donné (`scope`) : toute l'équipe (`global`), un commercial
 * (`rep`, scope_ref_id = users.id via sales_orders.sales_rep_id), un client
 * (`client`, scope_ref_id = crm_contacts.id ou accounts selon ce que
 * sales_orders.contact_id référence déjà), ou une catégorie de produits
 * (`category`, scope_ref_id = inventory_categories.id). `scope_ref_id` reste
 * volontairement sans FK contrainte, puisque la table qu'il référence dépend
 * de `scope` — même précédent déjà établi dans ce fichier pour les liens
 * cross-module souples (CostingSheet.opportunity_id, ProductionOrder.
 * sales_order_id).
 *
 * L'application propose 2-3 lignes par (scope, scope_ref_id, période) —
 * status='proposed' — que l'utilisateur peut modifier puis valider une seule
 * (status='validated'), les autres passant à 'rejected'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_objectives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('scope', 20); // global|rep|client|category
            $table->unsignedBigInteger('scope_ref_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_amount', 14, 2);
            $table->string('currency', 3)->default('MGA');
            $table->string('proposal_label', 40)->nullable(); // Conservateur/Modéré/Ambitieux
            $table->text('basis')->nullable(); // rationale (IA ou repli statique)
            $table->decimal('growth_rate_percent', 6, 2)->nullable();
            $table->string('status', 20)->default('proposed'); // proposed|validated|rejected
            $table->string('source', 10)->default('ai'); // ai|manual
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scope', 'scope_ref_id', 'period_start', 'period_end'], 'sales_objectives_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_objectives');
    }
};
