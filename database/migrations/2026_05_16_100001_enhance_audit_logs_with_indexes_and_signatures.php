<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns for enhanced security and retention
        if (Schema::hasTable('admin_audit_logs')) {
            Schema::table('admin_audit_logs', function (Blueprint $table) {
                // Add signature column for tamper detection
                if (!Schema::hasColumn('admin_audit_logs', 'signature')) {
                    $table->string('signature', 64)->nullable()->after('payload');
                    $table->index('signature');
                }

                // Add immutable flag to prevent modification
                if (!Schema::hasColumn('admin_audit_logs', 'is_immutable')) {
                    $table->boolean('is_immutable')->default(false)->after('signature');
                    $table->index('is_immutable');
                }

                // Add data expiration date for retention policy
                if (!Schema::hasColumn('admin_audit_logs', 'data_expires_at')) {
                    $table->timestamp('data_expires_at')->nullable()->after('is_immutable');
                    $table->index('data_expires_at');
                }

                // Add archived flag for archival tracking
                if (!Schema::hasColumn('admin_audit_logs', 'archived_at')) {
                    $table->timestamp('archived_at')->nullable()->after('data_expires_at');
                    $table->index('archived_at');
                }

                // Add module tracking
                if (!Schema::hasColumn('admin_audit_logs', 'module')) {
                    $table->string('module')->nullable()->after('resource_type');
                    $table->index('module');
                }
            });
        }

        // Add composite indexes for performance
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Composite index: (created_at DESC, module) - for today by module queries
            if (!Schema::hasColumn('admin_audit_logs', 'created_at')) {
                return;
            }

            $table->index(['created_at', 'module'], 'idx_created_module');

            // Composite index: (created_at DESC, user_id) - for user activity queries
            $table->index(['created_at', 'user_id'], 'idx_created_user');

            // Composite index: (created_at DESC, action) - for action filtering
            $table->index(['created_at', 'action'], 'idx_created_action');

            // Composite index: (resource_type, resource_id, created_at) - for resource history
            $table->index(['resource_type', 'resource_id', 'created_at'], 'idx_resource_history');

            // Single index for immutability checks
            $table->index(['is_immutable', 'created_at'], 'idx_immutable_created');
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            // Drop composite indexes
            $table->dropIndex('idx_created_module');
            $table->dropIndex('idx_created_user');
            $table->dropIndex('idx_created_action');
            $table->dropIndex('idx_resource_history');
            $table->dropIndex('idx_immutable_created');

            // Drop columns
            $table->dropColumn([
                'signature',
                'is_immutable',
                'data_expires_at',
                'archived_at',
                'module',
            ]);
        });
    }
};
