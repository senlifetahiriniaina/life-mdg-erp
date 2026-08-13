<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('key_name', 128);
            $table->string('key_type', 32)->index();
            $table->string('key_usage', 64);
            $table->string('key_status', 16)->default('active')->index();
            $table->string('key_material_hash', 128);
            $table->string('vault_reference', 256)->nullable();
            $table->unsignedInteger('key_length_bits')->default(256);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_encryption_keys');
    }
};
