<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('setup_import_jobs')->cascadeOnDelete();
            $table->string('source_field', 128)->index();
            $table->string('target_field', 128);
            $table->string('transform_type', 32)->nullable();
            $table->json('transform_config')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('ai_suggested')->default(false);
            $table->decimal('ai_confidence', 5, 2)->nullable();
            $table->boolean('user_confirmed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_field_mappings');
    }
};
