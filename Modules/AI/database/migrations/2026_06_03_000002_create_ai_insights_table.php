<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('module', 64);
            $table->string('entity_type', 128);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('insight_type', 64);   // anomaly|trend|recommendation|forecast
            $table->text('content');
            $table->decimal('confidence_score', 5, 4)->default(0.0);
            $table->boolean('is_read')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module', 'entity_type', 'entity_id']);
            $table->index(['is_read', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
