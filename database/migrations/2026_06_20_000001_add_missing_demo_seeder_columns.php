<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add columns referenced by models/DemoSeeder that were missing from their tables,
 * so the full DatabaseSeeder (and full-seed-dependent tests) run cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('doc_folders')) {
            Schema::table('doc_folders', function (Blueprint $table) {
                if (! Schema::hasColumn('doc_folders', 'color')) {
                    $table->string('color')->nullable();
                }
                if (! Schema::hasColumn('doc_folders', 'permissions')) {
                    $table->json('permissions')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('doc_folders')) {
            Schema::table('doc_folders', function (Blueprint $table) {
                foreach (['color', 'permissions'] as $col) {
                    if (Schema::hasColumn('doc_folders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
