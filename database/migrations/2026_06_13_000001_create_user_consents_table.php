<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_consents')) {
            Schema::create('user_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('tenant_id', 255);
                $table->string('type', 64);
                $table->boolean('granted')->default(true);
                $table->string('version', 32);
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('consented_at');
                $table->timestamp('withdrawn_at')->nullable();
    
                $table->index(['user_id', 'tenant_id'], 'idx_user_tenant');
                $table->unique(['user_id', 'tenant_id', 'type'], 'uq_user_tenant_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
