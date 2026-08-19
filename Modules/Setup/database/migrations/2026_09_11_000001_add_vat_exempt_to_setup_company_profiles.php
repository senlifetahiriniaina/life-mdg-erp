<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 13: `vat_number` already existed on this profile but nothing
 * recorded whether a company is subject to VAT at all — a real gap for
 * Madagascar SARLs below the VAT-registration turnover threshold, which
 * remain liable for other taxes (IR — impôt sur les bénéfices, already
 * modeled by the seeded `444` chart-of-accounts entry) without collecting
 * or deducting TVA at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('setup_company_profiles', 'vat_exempt')) {
            return;
        }

        Schema::table('setup_company_profiles', function (Blueprint $table) {
            $table->boolean('vat_exempt')->default(false)->after('vat_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('setup_company_profiles', 'vat_exempt')) {
            Schema::table('setup_company_profiles', function (Blueprint $table) {
                $table->dropColumn('vat_exempt');
            });
        }
    }
};
