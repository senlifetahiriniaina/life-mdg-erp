<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_contacts') && ! Schema::hasColumn('crm_contacts', 'tenant_id')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_contacts') && Schema::hasColumn('crm_contacts', 'tenant_id')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
