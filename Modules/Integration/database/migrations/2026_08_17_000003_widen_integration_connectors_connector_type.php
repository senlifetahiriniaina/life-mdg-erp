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

        // Legacy NOT NULL column from the original migration, superseded by
        // `provider_type` in the model's real $fillable — no real code path
        // populates `connector_type` anymore, so widen it to nullable.
        Schema::table('integration_connectors', function (Blueprint $table) {
            $table->string('connector_type', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connectors')) {
            return;
        }

        Schema::table('integration_connectors', function (Blueprint $table) {
            $table->string('connector_type', 64)->nullable(false)->change();
        });
    }
};
