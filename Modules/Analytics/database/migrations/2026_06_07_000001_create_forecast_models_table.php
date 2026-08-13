<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecast_models', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('name', 128);
            $table->string('module', 64)->index();
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 36)->nullable();
            $table->string('algorithm', 32)->default('holt_winters')->index();
            $table->unsignedSmallInteger('horizon_days')->default(30);
            $table->decimal('confidence_level', 5, 4)->default(0.9500);
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamp('next_retrain_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_models');
    }
};
