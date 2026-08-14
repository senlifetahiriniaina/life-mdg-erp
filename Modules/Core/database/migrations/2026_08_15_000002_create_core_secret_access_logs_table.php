<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Models\SecretAccessLog ($table = 'core_secret_access_logs',
 * $timestamps = false — it has its own 'timestamp' column) never had a
 * migration. secret_id must be nullable: SecretsService::logAccess() is
 * called with a null $secret on 'access_denied' when the name doesn't
 * resolve to any row at all. The (secret_id, action) index matches the
 * exact pair SecretsServiceTest::test_secret_access_logged() queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_secret_access_logs')) {
            Schema::create('core_secret_access_logs', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->nullable()->index();
                $table->string('secret_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 50)->index();
                $table->string('ip_address', 45)->nullable();
                $table->boolean('success')->default(false)->index();
                $table->text('reason')->nullable();
                $table->timestamp('timestamp')->nullable()->index();

                $table->index(['secret_id', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_secret_access_logs');
    }
};
