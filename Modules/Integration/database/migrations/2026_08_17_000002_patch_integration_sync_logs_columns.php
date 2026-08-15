<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('integration_sync_logs')) {
            return;
        }

        Schema::table('integration_sync_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_sync_logs', 'direction')) {
                $table->string('direction', 16)->nullable();
            }
            if (! Schema::hasColumn('integration_sync_logs', 'records_synced')) {
                // Additive: original migration named this column `records_processed`,
                // the model $fillable uses `records_synced`.
                $table->unsignedInteger('records_synced')->default(0);
            }
            if (! Schema::hasColumn('integration_sync_logs', 'errors')) {
                // Additive: original migration named this column `error_details`,
                // the model $fillable/casts use `errors`.
                $table->json('errors')->nullable();
            }
            if (! Schema::hasColumn('integration_sync_logs', 'connector_id')) {
                // The model's real $fillable/relationship uses `connector_id` (FK to
                // integration_connectors), a column the original migration never
                // created — it only has `integration_id` (FK to the unrelated
                // `integrations` table), which no real code populates.
                $table->unsignedBigInteger('connector_id')->nullable()->after('integration_id');
            }
            if (! Schema::hasColumn('integration_sync_logs', 'tenant_id')) {
                // Two real models share this table with divergent schemas:
                // IntegrationSyncLog (integration_id/records_synced/errors, used by
                // IntegrationManager) and SyncLog (connector_id/tenant_id/
                // records_processed/error_details, used by IntegrationService).
                // tenant_id matches integration_connectors.tenant_id's type (string(36)).
                $table->string('tenant_id', 36)->nullable();
            }
            if (! Schema::hasColumn('integration_sync_logs', 'payload_size')) {
                $table->unsignedInteger('payload_size')->nullable();
            }
        });

        // Legacy NOT NULL/constrained columns from the original migration that no
        // real code path populates anymore (superseded by `connector_id`/`direction`
        // above) — widen so real inserts stop failing on them.
        Schema::table('integration_sync_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('integration_id')->nullable()->change();
            $table->string('sync_type', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_sync_logs')) {
            return;
        }

        Schema::table('integration_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['direction', 'records_synced', 'errors', 'connector_id']);
        });
    }
};
