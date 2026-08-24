<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32 (volet C) — stocke le chemin du fichier original (image/PDF)
 * d'une facture fournisseur capturée par scan, pattern déjà établi par
 * `EmployeeDocument.file_path` — pas spatie/laravel-medialibrary (dépendance
 * installée mais jamais adoptée nulle part dans l'app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acc_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('acc_invoices', 'scan_path')) {
                $table->string('scan_path')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('acc_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('acc_invoices', 'scan_path')) {
                $table->dropColumn('scan_path');
            }
        });
    }
};
