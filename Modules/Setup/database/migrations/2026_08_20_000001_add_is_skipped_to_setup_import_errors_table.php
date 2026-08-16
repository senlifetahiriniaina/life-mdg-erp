<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('setup_import_errors') && ! Schema::hasColumn('setup_import_errors', 'is_skipped')) {
            Schema::table('setup_import_errors', function (Blueprint $table) {
                // Additive: the model's $fillable declares is_skipped but the
                // original migration never created the column.
                $table->boolean('is_skipped')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('setup_import_errors') && Schema::hasColumn('setup_import_errors', 'is_skipped')) {
            Schema::table('setup_import_errors', function (Blueprint $table) {
                $table->dropColumn('is_skipped');
            });
        }
    }
};
