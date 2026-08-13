<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('anomaly_detection_models', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('name', 128);
            $table->string('module', 64)->index();
            $table->string('metric_key', 128);
            $table->string('algorithm', 32)->default('isolation_forest');
            $table->decimal('threshold', 8, 4)->default(2.0000);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_detection_models');
    }
};
