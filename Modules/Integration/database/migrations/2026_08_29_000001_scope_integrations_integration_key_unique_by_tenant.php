<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 32.6 (Integration deep audit): `integrations.integration_key` had
 * a bare, GLOBAL unique index (2026_06_07_000001_create_integrations_table.php)
 * — confirmed empirically via tinker that a second tenant in this multi-
 * tenant app trying to connect to the same integration (e.g. 'orange-money',
 * the exact Africa-First mobile-money connector CLAUDE.md documents as a
 * real feature) fatals with a UniqueConstraintViolationException on
 * IntegrationManager::connect(), since Integration::updateOrCreate()
 * matches on [tenant_id, integration_key] but only integration_key alone
 * was ever constrained at the DB level. Every tenant after the very first
 * one to ever connect a given integration key on this server has been
 * permanently blocked from doing so.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integrations')) {
            return;
        }

        if ($this->indexExists('integrations', 'integrations_integration_key_unique')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->dropUnique('integrations_integration_key_unique');
            });
        }

        if (! $this->indexExists('integrations', 'integrations_tenant_id_integration_key_unique')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->unique(['tenant_id', 'integration_key'], 'integrations_tenant_id_integration_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('integrations')) {
            return;
        }

        if ($this->indexExists('integrations', 'integrations_tenant_id_integration_key_unique')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->dropUnique('integrations_tenant_id_integration_key_unique');
            });
        }

        if (! $this->indexExists('integrations', 'integrations_integration_key_unique')) {
            Schema::table('integrations', function (Blueprint $table) {
                $table->unique('integration_key');
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
