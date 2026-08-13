<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions_enhanced')) {
            Schema::create('sessions_enhanced', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address')->nullable();
                $table->string('user_agent_hash')->nullable();
                $table->string('device_fingerprint')->nullable();
                $table->string('browser_fingerprint')->nullable();
                $table->string('device_type')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('fingerprint_checked_at')->nullable();
                $table->integer('regeneration_count')->default(0);
                $table->integer('concurrent_session_number')->default(1);
                $table->integer('suspicious_activity_count')->default(0);
                $table->string('tenant_id')->nullable()->index();
            });
        }

        if (! Schema::hasTable('session_security_events')) {
            Schema::create('session_security_events', function (Blueprint $table) {
                $table->id();
                $table->string('session_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('event_type')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('old_fingerprint')->nullable();
                $table->string('new_fingerprint')->nullable();
                $table->text('reason')->nullable();
                $table->string('severity')->default('info');
                $table->string('action_taken')->default('none');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->string('tenant_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('session_security_events');
        Schema::dropIfExists('sessions_enhanced');
    }
};
