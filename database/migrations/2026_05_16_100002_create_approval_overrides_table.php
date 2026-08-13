<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('original_approval_id');
            $table->text('reason');
            $table->string('approval_type'); // invoice, expense, purchase_order, payment, etc.
            $table->unsignedBigInteger('related_resource_id')->nullable();
            $table->string('related_resource_type')->nullable();
            $table->timestamps();

            $table->index(['approver_id', 'created_at']);
            $table->index(['approval_type', 'created_at']);
            $table->index(['related_resource_type', 'related_resource_id']);
            $table->index('created_at');
        });

        // Create audit log access tracking table
        Schema::create('audit_log_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('audit_log_id')->constrained('admin_audit_logs')->onDelete('cascade');
            $table->string('action')->default('read'); // read, export, download
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['audit_log_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_access_logs');
        Schema::dropIfExists('approval_overrides');
    }
};
