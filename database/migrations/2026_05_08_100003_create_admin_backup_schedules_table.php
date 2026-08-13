<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_backup_schedules')) {
            return;
        }

        Schema::create('admin_backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['database', 'files', 'full']);
            $table->enum('frequency', ['hourly', 'daily', 'weekly', 'monthly']);
            $table->string('time_of_day')->nullable(); // e.g. "02:00"
            $table->unsignedInteger('retention_days')->default(30);
            $table->enum('storage_driver', ['local', 's3', 'gcs', 'azure_blob'])->default('local');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_backup_schedules');
    }
};
