<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * crm_contacts_email_unique was a bare, global unique index on email
 * (2026_06_19_000006_create_crm_companies_and_contact_cols.php), added
 * before tenant_id existed on this table (added later in
 * 2026_06_20_000002_add_tenant_id_to_crm_contacts.php). It was never
 * revisited to become tenant-scoped, so two different tenants could not
 * share the same contact email -- a genuine multi-tenant isolation bug,
 * not just a test artifact.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_contacts') || ! Schema::hasColumn('crm_contacts', 'tenant_id')) {
            return;
        }

        if ($this->indexExists('crm_contacts', 'crm_contacts_email_unique')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->dropUnique('crm_contacts_email_unique');
            });
        }

        if (! $this->indexExists('crm_contacts', 'crm_contacts_tenant_id_email_unique')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->unique(['tenant_id', 'email'], 'crm_contacts_tenant_id_email_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_contacts')) {
            return;
        }

        if ($this->indexExists('crm_contacts', 'crm_contacts_tenant_id_email_unique')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->dropUnique('crm_contacts_tenant_id_email_unique');
            });
        }

        if (! $this->indexExists('crm_contacts', 'crm_contacts_email_unique')) {
            Schema::table('crm_contacts', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(\DB::select("PRAGMA index_list({$table})") ?? [])
                ->contains(fn ($i) => ($i->name ?? null) === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
