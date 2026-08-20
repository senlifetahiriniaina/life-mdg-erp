<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 22 (volet B) — miroir côté fournisseur de la migration Sales
 * sœur : commande de matières avec acompte 30% / solde 70% à la livraison.
 * FK contrainte vers acc_invoices sûre pour la même raison (Accounting
 * boot avant Achats, et l'ordre d'exécution des migrations est de toute
 * façon global par timestamp de fichier, pas par module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
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
        Schema::table('achats_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('balance_invoice_id');
            $table->dropConstrainedForeignId('deposit_invoice_id');
            $table->dropColumn(['deposit_percent', 'deposit_required_amount']);
        });
    }
};
