<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admin_server_configs')) {
            Schema::create('admin_server_configs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('provider', ['gcp','aws','azure','digitalocean','hetzner','ovh','custom'])->default('custom');
                $table->string('region')->nullable();
                $table->string('instance_type')->nullable();
                $table->string('ip_address')->nullable();
                $table->enum('status', ['active','stopped','maintenance','unknown'])->default('unknown');
                $table->text('credentials_encrypted')->nullable();
                $table->string('api_endpoint')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('last_ping_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admin_backups')) {
            Schema::create('admin_backups', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['database','files','full'])->default('database');
                $table->enum('status', ['pending','running','completed','failed'])->default('pending');
                $table->bigInteger('size_bytes')->nullable();
                $table->string('file_path')->nullable();
                $table->enum('storage_driver', ['local','s3','gcs','azure_blob'])->default('local');
                $table->text('notes')->nullable();
                $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admin_backup_schedules')) {
            Schema::create('admin_backup_schedules', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['database','files','full'])->default('database');
                $table->enum('frequency', ['hourly','daily','weekly','monthly'])->default('daily');
                $table->string('time_of_day')->nullable();
                $table->integer('retention_days')->default(30);
                $table->enum('storage_driver', ['local','s3','gcs','azure_blob'])->default('local');
                $table->boolean('enabled')->default(true);
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admin_audit_logs')) {
            Schema::create('admin_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('resource_type')->nullable();
                $table->bigInteger('resource_id')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['user_id','action','created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_backup_schedules');
        Schema::dropIfExists('admin_backups');
        Schema::dropIfExists('admin_server_configs');
    }
};
