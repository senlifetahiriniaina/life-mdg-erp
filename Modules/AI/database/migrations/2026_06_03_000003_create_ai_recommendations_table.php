<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 64);
            $table->string('recommendation_type', 128);
            $table->string('title', 255);
            $table->text('description');
            $table->string('priority', 16)->default('medium');   // low|medium|high|critical
            $table->string('status', 32)->default('pending');    // pending|accepted|dismissed|implemented
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'status']);
            $table->index(['module', 'priority']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
