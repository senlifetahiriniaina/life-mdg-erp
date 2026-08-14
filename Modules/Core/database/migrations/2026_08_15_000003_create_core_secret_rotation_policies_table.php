<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Models\SecretRotationPolicy ($table = 'core_secret_rotation_policies')
 * never had a migration. Plain (non-unique) index on secret_id: the model
 * relation is hasOne and SecretsService::createRotationPolicy() only ever
 * runs once per secret today, but a unique constraint would turn any future
 * re-policy flow into a hard 500 instead of a normal upsert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_secret_rotation_policies')) {
            Schema::create('core_secret_rotation_policies', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->nullable()->index();
                $table->string('secret_id')->index();
                $table->unsignedInteger('rotation_interval')->default(30);
                $table->timestamp('last_rotation_at')->nullable();
                $table->timestamp('next_rotation_at')->nullable()->index();
                $table->boolean('auto_rotate')->default(true)->index();
                $table->json('notification_days_before')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_secret_rotation_policies');
    }
};
