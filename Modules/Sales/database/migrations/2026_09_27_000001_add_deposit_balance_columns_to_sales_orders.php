<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 22 — volet B de la feuille de route Chantier 21 : cycle
 * acompte/solde. Un acompte (typiquement 30-50%) est facturé et encaissé
 * avant le lancement de la production, le solde à la livraison. Les deux
 * factures réelles (Modules\Accounting\Models\Invoice) sont liées ici plutôt
 * que dupliquées — deposit_required_amount est un snapshot du montant
 * demandé au moment de la requête (le pourcentage seul ne suffit pas à
 * reconstruire l'historique si le total de la commande change ensuite).
 *
 * FK non contrainte volontaire vers acc_invoices : Accounting boot avant
 * Sales dans config/modules_statuses.json, donc la table existe déjà au
 * moment où cette migration s'exécute (confirmé : nwidart/laravel-modules
 * trie TOUTES les migrations ensemble par timestamp de fichier, pas par
 * module — vendor/laravel/framework Migrator::getMigrationFiles()), une FK
 * contrainte est donc sûre ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('deposit_percent', 5, 2)->nullable()->after('total');
            $table->decimal('deposit_required_amount', 14, 2)->nullable()->after('deposit_percent');
            $table->foreignId('deposit_invoice_id')->nullable()->after('deposit_required_amount')
                ->constrained('acc_invoices')->nullOnDelete();
            $table->foreignId('balance_invoice_id')->nullable()->after('deposit_invoice_id')
                ->constrained('acc_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('balance_invoice_id');
            $table->dropConstrainedForeignId('deposit_invoice_id');
            $table->dropColumn(['deposit_percent', 'deposit_required_amount']);
        });
    }
};
