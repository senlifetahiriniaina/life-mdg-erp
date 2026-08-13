<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detected_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anomaly_detection_model_id')->constrained('anomaly_detection_models')->cascadeOnDelete();
            $table->decimal('metric_value', 18, 4);
            $table->decimal('expected_value', 18, 4);
            $table->decimal('deviation_score', 8, 4);
            $table->string('severity', 16)->index();
            $table->string('status', 16)->default('open')->index();
            $table->json('context')->nullable();
            $table->timestamp('detected_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_anomalies');
    }
};
