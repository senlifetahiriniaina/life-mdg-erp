<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhbPermission's $fillable (data_type, can_receive, can_send, auto_accept)
 * has never matched what 2026_06_07_000007 actually created (resource_type,
 * permission_level, is_granted) — those 3 original columns are referenced
 * nowhere outside that migration. Every real caller (WhbPartnerService::
 * approveConnection()'s updateOrCreate(), and the new federation exchange
 * receiver) has always used the data_type/can_send/can_receive/auto_accept
 * shape, so any write to this table has been fataling since the feature
 * was first wired up. Additive only — the old columns are left in place
 * rather than dropped, matching this codebase's established migration
 * convention.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('whb_permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('whb_permissions', 'data_type')) {
                $table->string('data_type', 64)->nullable()->after('connection_id')->index();
            }
            if (! Schema::hasColumn('whb_permissions', 'can_receive')) {
                $table->boolean('can_receive')->default(false)->after('data_type');
            }
            if (! Schema::hasColumn('whb_permissions', 'can_send')) {
                $table->boolean('can_send')->default(false)->after('can_receive');
            }
            if (! Schema::hasColumn('whb_permissions', 'auto_accept')) {
                $table->boolean('auto_accept')->default(false)->after('can_send');
            }
        });

        if (! $this->hasIndex('whb_permissions', 'whb_permissions_connection_id_data_type_unique')) {
            Schema::table('whb_permissions', function (Blueprint $table) {
                $table->unique(['connection_id', 'data_type'], 'whb_permissions_connection_id_data_type_unique');
            });
        }

        // resource_type was NOT NULL with no default, and nothing populates
        // it — every insert through the real (data_type-based) write path
        // was failing a constraint that belongs to a column no application
        // code sets. Relax it rather than backfilling a value nobody reads.
        Schema::table('whb_permissions', function (Blueprint $table) {
            $table->string('resource_type', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('whb_permissions', function (Blueprint $table) {
            $table->dropUnique('whb_permissions_connection_id_data_type_unique');
            $table->dropColumn(['data_type', 'can_receive', 'can_send', 'auto_accept']);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
