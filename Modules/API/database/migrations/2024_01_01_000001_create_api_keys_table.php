<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('name');
                $table->string('key_hash', 64)->unique();
                $table->string('key_prefix', 8)->index();
                $table->json('scopes')->nullable();
                $table->unsignedInteger('rate_limit')->default(1000)->comment('requests per hour');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('description')->nullable();
                $table->json('allowed_ips')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'revoked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
