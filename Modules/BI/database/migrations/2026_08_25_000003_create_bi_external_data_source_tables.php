<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.2 (BI): `ExternalDataSourceController` (18 methods — external
 * integrations: connect/disconnect, credentials, field mapping, sync
 * config, transformation rules, sync history) and its policy
 * (`ExternalDataPolicy`) are fully written and already call into
 * `Modules\BI\Models\{ExternalDataSource,ExternalCredential,FieldMapping,
 * SyncConfiguration,SyncHistory,TransformationRule}` — all six models have
 * real `$fillable`/`$casts` but no migration ever created their tables.
 * Creates all six now, matching every other model-with-no-table gap already
 * fixed elsewhere in this codebase (see CLAUDE.md's Chantier 8.1/8.2 notes).
 *
 * `ExternalCredential::encrypted_value` mirrors `BiDataSource::connection_config`'s
 * `text` column choice for an encrypted payload (here via manual
 * encrypt()/decrypt() rather than an `encrypted` cast, but the same
 * "don't truncate a ciphertext" reasoning applies).
 *
 * Note: `ExternalDataSource::dataRefreshSchedule()` references a seventh
 * model, `DataRefreshSchedule` (table `bi_data_refresh_schedules`) — that
 * model/table is out of this migration's scope (not one of the 6 models
 * driving `ExternalDataSourceController`) and is left untouched here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bi_external_data_sources')) {
            Schema::create('bi_external_data_sources', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('source_type');
                $table->string('status')->default('inactive')->index();
                $table->json('connection_config')->nullable();
                $table->string('authentication_type');
                $table->boolean('is_test_connection')->default(false);
                $table->timestamp('last_test_at')->nullable();
                $table->timestamp('last_successful_sync')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('bi_external_credentials')) {
            Schema::create('bi_external_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('credential_type');
                $table->text('encrypted_value');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_field_mappings')) {
            Schema::create('bi_field_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('source_field');
                $table->string('target_field');
                $table->string('data_type');
                $table->text('transformation_rule')->nullable();
                $table->boolean('is_primary_key')->default(false);
                $table->boolean('is_mapped')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_sync_configurations')) {
            Schema::create('bi_sync_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('sync_type');
                $table->string('frequency');
                $table->time('scheduled_time')->nullable();
                $table->string('day_of_week')->nullable();
                $table->unsignedTinyInteger('day_of_month')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('batch_size')->default(100);
                $table->unsignedInteger('max_retries')->default(3);
                $table->unsignedInteger('retry_delay_minutes')->default(5);
                $table->json('filter_criteria')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_sync_history')) {
            Schema::create('bi_sync_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('status')->default('pending')->index();
                $table->string('sync_type')->nullable();
                $table->unsignedInteger('records_attempted')->default(0);
                $table->unsignedInteger('records_synced')->default(0);
                $table->unsignedInteger('records_failed')->default(0);
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->decimal('data_size_mb', 10, 2)->nullable();
                $table->text('error_message')->nullable();
                $table->json('error_log')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bi_transformation_rules')) {
            Schema::create('bi_transformation_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('source_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('rule_order')->default(0);
                $table->string('rule_type');
                $table->json('rule_config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_transformation_rules');
        Schema::dropIfExists('bi_sync_history');
        Schema::dropIfExists('bi_sync_configurations');
        Schema::dropIfExists('bi_field_mappings');
        Schema::dropIfExists('bi_external_credentials');
        Schema::dropIfExists('bi_external_data_sources');
    }
};
