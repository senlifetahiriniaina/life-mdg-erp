<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 26 (volet B) — objectifs commerciaux assistés par IA. Le module
 * n'avait aucun champ identifiant "quel commercial" est responsable d'une
 * commande — seul `created_by` existe (qui a saisi la commande dans
 * l'application, pas nécessairement le commercial en charge du compte : un
 * admin peut saisir pour un commercial, ou un commercial peut saisir pour un
 * collègue absent). `sales_rep_id` est un second champ, nullable et
 * indépendant, populé par défaut depuis `created_by` mais explicitement
 * modifiable — c'est la vraie dimension "par commercial" nécessaire pour
 * répartir un objectif d'équipe entre commerciaux individuels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_rep_id');
        });
    }
};
