<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_source_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('setup_import_jobs')->cascadeOnDelete()->unique();
            $table->json('columns');
            $table->json('sample_data')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->string('detected_encoding', 32)->default('UTF-8');
            $table->string('detected_delimiter', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_source_schemas');
    }
};
