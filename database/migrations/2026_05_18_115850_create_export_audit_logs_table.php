<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('export_audit_logs')) {
            Schema::create('export_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users');
                $table->string('export_type');
                $table->string('entity_type');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('filename');
                $table->uuid('export_id')->unique();
                $table->string('format')->indexed();
                $table->unsignedInteger('row_count');
                $table->unsignedInteger('file_size_bytes');
                $table->boolean('includes_sensitive_data')->default(false);
                $table->ipAddress('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->json('filters_applied')->nullable();
                $table->enum('status', ['success', 'failure', 'cancelled'])->default('success');
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['export_type', 'created_at']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_audit_logs');
    }
};
