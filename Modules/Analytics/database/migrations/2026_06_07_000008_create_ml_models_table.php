<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('name', 128);
            $table->string('model_type', 64)->index();
            $table->string('module', 64)->index();
            $table->string('status', 16)->default('training')->index();
            $table->decimal('accuracy_score', 5, 4)->nullable();
            $table->json('hyperparameters')->nullable();
            $table->string('model_path', 512)->nullable();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
