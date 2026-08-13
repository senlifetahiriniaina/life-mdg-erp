<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_embed_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dashboard_id')->index();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('token_hash');               // bcrypt hash for revocation
            $table->string('jti')->unique();             // JWT ID — fast revocation lookup
            $table->json('allowed_domains');             // ["example.com", "app.acme.io"]
            $table->timestamp('expires_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('revoked_at')->nullable(); // NULL = active
            $table->timestamps();

            $table->index(['tenant_id', 'dashboard_id']);
            $table->index(['tenant_id', 'revoked_at', 'expires_at'], 'bi_embed_tokens_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_embed_tokens');
    }
};
