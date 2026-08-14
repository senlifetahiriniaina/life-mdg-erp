<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modules\Core\Models\SecretAccessGrant ($table = 'core_secret_access_grants')
 * never had a migration. Its absence made SecretAccessControl::canAccessSecret()
 * fail silently: the QueryException from the missing-table lookup was caught
 * by a blanket try/catch and converted to a deny for every non-admin user
 * (admins short-circuit before reaching this query) — this table existing
 * is the entire fix for that path, no code change needed there. The
 * (secret_id, user_id) index matches the hot lookup in that method.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_secret_access_grants')) {
            Schema::create('core_secret_access_grants', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->nullable()->index();
                $table->string('secret_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->json('scopes')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('revoked_at')->nullable()->index();
                $table->unsignedBigInteger('granted_by')->nullable()->index();
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['secret_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_secret_access_grants');
    }
};
