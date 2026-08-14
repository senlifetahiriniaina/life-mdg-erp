<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Models\Secret has always declared $table = 'core_secrets',
 * but no migration anywhere ever created it — SecretsService has been
 * completely dormant since it was written (see Phase 5 of the session plan
 * for the full inventory). encrypted_value is longText, not text: the
 * "SEC1.<version>.<payload>" envelope EncryptionService produces roughly
 * 2.4x-expands the plaintext (base64 of a JSON {iv,value,mac} blob), and
 * Modules/Core/config/secrets.php allows up to 65535-byte values — right at
 * TEXT's ceiling, where truncation would silently corrupt a stored secret.
 * unique(tenant_id, name) backstops the manual pre-check in
 * SecretsService::storeSecret(), which is otherwise a TOCTOU race under
 * concurrent writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_secrets')) {
            Schema::create('core_secrets', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->index();
                $table->string('name');
                $table->string('type', 50)->index();
                $table->longText('encrypted_value');
                $table->unsignedInteger('key_version')->default(1);
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('rotated_at')->nullable();
                $table->timestamp('next_rotation')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('tags')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_secrets');
    }
};
