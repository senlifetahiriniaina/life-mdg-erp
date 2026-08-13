<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecast_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_model_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type', 32)->index();
            $table->string('severity', 16)->default('medium')->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('triggered_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_alerts');
    }
};
