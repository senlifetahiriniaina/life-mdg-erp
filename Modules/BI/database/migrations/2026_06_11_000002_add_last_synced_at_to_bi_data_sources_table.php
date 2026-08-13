<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds last_synced_at to bi_data_sources (tracks when data was last fetched
 * via DataSourceService::fetchData).
 *
 * Safe to run even if the column already exists — guarded by hasColumn check.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bi_data_sources') && ! Schema::hasColumn('bi_data_sources', 'last_synced_at')) {
            Schema::table('bi_data_sources', function (Blueprint $table) {
                $table->timestamp('last_synced_at')->nullable()->after('last_tested_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bi_data_sources') && Schema::hasColumn('bi_data_sources', 'last_synced_at')) {
            Schema::table('bi_data_sources', function (Blueprint $table) {
                $table->dropColumn('last_synced_at');
            });
        }
    }
};
