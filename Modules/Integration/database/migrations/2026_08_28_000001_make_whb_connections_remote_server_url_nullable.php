<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19 Lot 3: `whb_connections.remote_server_url` was NOT NULL from
 * its very first migration, but WhbPartnerService::createInvite() never
 * supplies one for a `local` (same-server, cross-tenant) connection — only
 * `remote` connections have a real server URL. Confirmed empirically via a
 * real HTTP request that every "local" WHB federation invite has therefore
 * always failed with a NOT NULL constraint violation, on top of the
 * separate phantom-users.tenant_id bug fixed in the same service in this
 * same pass. WhbFederationService::sendInvite/sendAccept/sendExchange/
 * sendRefresh already all guard `if (! $connection->remote_server_url)` and
 * no-op — the application logic was already written to expect this column
 * can be empty for a local connection, only the schema disagreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whb_connections')) {
            return;
        }

        Schema::table('whb_connections', function (Blueprint $table): void {
            $table->string('remote_server_url', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('whb_connections')) {
            return;
        }

        Schema::table('whb_connections', function (Blueprint $table): void {
            $table->string('remote_server_url', 512)->nullable(false)->change();
        });
    }
};
