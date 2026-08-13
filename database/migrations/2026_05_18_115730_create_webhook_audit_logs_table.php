<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('webhook_audit_logs')) {
            Schema::create('webhook_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->indexed();
                $table->string('event_type')->nullable();
                $table->enum('status', ['success', 'failure', 'invalid_signature', 'replay_detected', 'rate_limited']);
                $table->ipAddress('ip_address')->nullable();
                $table->json('request_headers')->nullable();
                $table->integer('response_code');
                $table->text('error_message')->nullable();
                $table->integer('processing_time_ms')->nullable();
                $table->string('nonce')->nullable()->unique();
                $table->timestamp('timestamp_received')->nullable();
                $table->timestamps();
                $table->index(['provider', 'status', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_audit_logs');
    }
};
