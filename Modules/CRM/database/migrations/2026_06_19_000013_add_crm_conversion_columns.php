<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_leads')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_leads', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('crm_leads', 'converted_to_contact_id')) {
                    $table->unsignedBigInteger('converted_to_contact_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('crm_contacts') && ! Schema::hasColumn('crm_contacts', 'lead_id')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->unsignedBigInteger('lead_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_opportunities', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('crm_opportunities', 'lost_reason')) {
                    $table->string('lost_reason')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_leads')) {
            Schema::table('crm_leads', function (Blueprint $table) {
                foreach (['company_id', 'converted_to_contact_id'] as $col) {
                    if (Schema::hasColumn('crm_leads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('crm_contacts') && Schema::hasColumn('crm_contacts', 'lead_id')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->dropColumn('lead_id');
            });
        }
        if (Schema::hasTable('crm_opportunities')) {
            Schema::table('crm_opportunities', function (Blueprint $table) {
                foreach (['title', 'lost_reason'] as $col) {
                    if (Schema::hasColumn('crm_opportunities', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
