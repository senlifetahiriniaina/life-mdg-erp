<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('name', 128);
            $table->string('source_type', 32)->index();
            $table->string('source_file_path', 512)->nullable();
            $table->string('source_db_driver', 32)->nullable();
            $table->text('source_db_config')->nullable();
            $table->string('target_module', 64)->index();
            $table->string('target_entity', 64);
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('error_summary')->nullable();
            $table->boolean('ai_mapping_used')->default(false);
            $table->decimal('ai_mapping_confidence', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_import_jobs');
    }
};
