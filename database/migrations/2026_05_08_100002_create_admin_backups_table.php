<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_backups')) {
            return;
        }

        Schema::create('admin_backups', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['database', 'files', 'full']);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('file_path')->nullable();
            $table->enum('storage_driver', ['local', 's3', 'gcs', 'azure_blob'])->default('local');
            $table->text('notes')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_backups');
    }
};
