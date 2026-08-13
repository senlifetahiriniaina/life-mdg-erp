<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ml_model_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ml_model_id')->constrained()->cascadeOnDelete();
            $table->string('version', 16)->index();
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->json('metrics')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_model_versions');
    }
};
