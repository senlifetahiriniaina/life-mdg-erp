<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('integration_connectors')) {
            return;
        }

        Schema::table('integration_connectors', function (Blueprint $table) {
            if (! Schema::hasColumn('integration_connectors', 'slug')) {
                $table->string('slug', 160)->nullable()->after('name');
            }
            if (! Schema::hasColumn('integration_connectors', 'provider_type')) {
                // Additive: the original migration named this column `connector_type`,
                // the model $fillable uses `provider_type` — added as a new column
                // rather than renaming (additive-only migration policy).
                $table->string('provider_type', 64)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('integration_connectors', 'last_sync_at')) {
                $table->timestamp('last_sync_at')->nullable();
            }
            if (! Schema::hasColumn('integration_connectors', 'error_message')) {
                $table->text('error_message')->nullable();
            }
            if (! Schema::hasColumn('integration_connectors', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (! Schema::hasColumn('integration_connectors', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connectors')) {
            return;
        }

        Schema::table('integration_connectors', function (Blueprint $table) {
            $table->dropColumn(['slug', 'provider_type', 'last_sync_at', 'error_message', 'created_by', 'deleted_at']);
        });
    }
};
