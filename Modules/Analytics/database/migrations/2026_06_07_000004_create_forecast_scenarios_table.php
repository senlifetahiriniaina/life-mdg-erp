<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecast_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_model_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->json('assumptions');
            $table->json('results')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_scenarios');
    }
};
