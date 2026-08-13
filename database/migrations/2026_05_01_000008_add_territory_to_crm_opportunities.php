<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add territory_id to crm_opportunities
        if (Schema::hasTable('crm_opportunities') && !Schema::hasColumn('crm_opportunities', 'territory_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                $table->foreignId('territory_id')
                    ->nullable()
                    ->constrained('crm_territories')
                    ->nullOnDelete()
                    ->after('pipeline_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_opportunities') && Schema::hasColumn('crm_opportunities', 'territory_id')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                $table->dropForeignKeyIfExists(['territory_id']);
                $table->dropColumn('territory_id');
            });
        }
    }
};
