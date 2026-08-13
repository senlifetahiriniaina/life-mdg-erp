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
        // Update gdpr_requests table
        if (Schema::hasTable('gdpr_requests')) {
            Schema::table('gdpr_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('gdpr_requests', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('requested_at');
                }
                if (!Schema::hasColumn('gdpr_requests', 'failure_reason')) {
                    $table->text('failure_reason')->nullable()->after('completed_at');
                }
            });
        } else {
            Schema::create('gdpr_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('request_type', ['sar', 'deletion', 'rectification', 'portability']);
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->timestamp('requested_at');
                $table->timestamp('completed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index('created_at');
            });
        }

        // Create gdpr_exports table
        Schema::create('gdpr_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('request_id')->constrained('gdpr_requests')->cascadeOnDelete();
            $table->string('file_path')->comment('Path in exports disk');
            $table->bigInteger('file_size')->nullable()->comment('Size in bytes');
            $table->timestamp('expires_at')->comment('Auto-delete after this date');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expires_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdpr_exports');

        // Only drop gdpr_requests if we created it
        if (Schema::hasTable('gdpr_requests')) {
            Schema::table('gdpr_requests', function (Blueprint $table) {
                // This was a new migration, so we can safely drop
            });
        }
    }
};
