<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_sync_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('calendar_calendars')->cascadeOnDelete();
            $table->string('provider', 32)->index();
            $table->string('access_token', 2048)->nullable();
            $table->string('refresh_token', 512)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('sync_token', 512)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['calendar_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_tokens');
    }
};
