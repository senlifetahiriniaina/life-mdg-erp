<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Traits\HasPasswordHistory -- OWASP password-reuse prevention.
 * Only stores bcrypt/argon2 hashes (same as users.password), never plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('password_histories')) {
            Schema::create('password_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('password_hash');
                $table->timestamp('created_at')->nullable();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
