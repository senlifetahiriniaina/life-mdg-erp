<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32 (volet A1) — `acc_fiscal_years` had no tenant boundary at
 * all (only the dead scaffold `tenant_id` column, never used by the real
 * `FiscalYear` model). `acc_journal_entries.fiscal_year_id` already exists
 * from an earlier scaffold pass (confirmed via Schema::getColumnListing)
 * and is already in `JournalEntry::$fillable` — no migration needed for
 * that side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acc_fiscal_years', function (Blueprint $table) {
            if (! Schema::hasColumn('acc_fiscal_years', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->index()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('acc_fiscal_years', function (Blueprint $table) {
            if (Schema::hasColumn('acc_fiscal_years', 'company_id')) {
                $table->dropColumn('company_id');
            }
        });
    }
};
