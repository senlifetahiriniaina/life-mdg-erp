<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_anomalies', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64);
            $table->string('entity_type', 128);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('anomaly_type', 64);   // duplicate|outlier|threshold_breach|pattern_deviation
            $table->string('severity', 16)->default('medium');  // low|medium|high|critical
            $table->text('description');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();

            $table->index(['module', 'entity_type', 'entity_id']);
            $table->index(['severity', 'detected_at']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_anomalies');
    }
};
