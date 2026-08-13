<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('setup_import_jobs')->cascadeOnDelete();
            $table->unsignedInteger('row_number')->index();
            $table->string('field_name', 128)->nullable();
            $table->string('error_type', 64)->index();
            $table->text('error_message');
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_import_errors');
    }
};
