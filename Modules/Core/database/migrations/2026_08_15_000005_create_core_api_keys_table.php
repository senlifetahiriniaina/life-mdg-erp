<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Models\ApiKey ($table = 'core_api_keys') never had a
 * migration — needed by SecretAccessControl::generateApiKey()/verifyApiKey()/
 * revokeApiKey() (service-account keys, a separate concern from the secrets
 * vault proper). ip_restrictions is a comma-separated string, matching
 * ApiKey::checkIpRestriction()'s explode(',').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_api_keys')) {
            Schema::create('core_api_keys', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name');
                $table->string('key_hash');
                $table->string('key_preview', 20);
                $table->json('scopes')->nullable();
                $table->string('ip_restrictions')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_api_keys');
    }
};
