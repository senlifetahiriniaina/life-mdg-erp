<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whb_connections', function (Blueprint $table) {
            $table->id();
            $table->string('local_tenant_id', 36)->index();
            $table->string('remote_tenant_id', 36)->nullable();
            $table->string('remote_server_url', 512);
            $table->string('remote_tenant_name', 128)->nullable();
            $table->string('connection_type', 32)->default('bidirectional');
            $table->string('status', 16)->default('pending')->index();
            $table->string('invite_code', 64)->unique()->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->string('shared_secret', 256)->nullable();
            $table->string('session_token', 256)->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->string('public_key', 2048)->nullable();
            $table->string('initiated_by', 36)->nullable();
            $table->string('approved_by', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whb_connections');
    }
};
