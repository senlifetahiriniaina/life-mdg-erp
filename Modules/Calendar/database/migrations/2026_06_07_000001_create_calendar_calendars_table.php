<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('color', 7)->default('#3B82F6');
            $table->string('type', 32)->default('personal')->index();
            $table->string('source', 32)->default('internal')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_visible')->default(true)->index();
            $table->string('sync_token', 256)->nullable();
            $table->string('external_calendar_id', 512)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_calendars');
    }
};
