<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// validation_level_approvers was only ever created as a generic stub table
// — this adds the real columns LevelApprover's model has always declared,
// plus `role`/`backup_role` (new: a level's approver can now be "whoever
// holds this role" instead of only a specific user_id, and a backup can be
// role-based too so ApprovalRoutingResolver can always resolve a fallback).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_level_approvers')) {
            Schema::table('validation_level_approvers', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_level_approvers', 'hierarchy_level_id')) {
                    $table->unsignedBigInteger('hierarchy_level_id')->nullable()->index();
                }
                if (! Schema::hasColumn('validation_level_approvers', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('validation_level_approvers', 'role')) {
                    $table->string('role', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('validation_level_approvers', 'approver_order')) {
                    $table->integer('approver_order')->default(0);
                }
                if (! Schema::hasColumn('validation_level_approvers', 'backup_user_id')) {
                    $table->unsignedBigInteger('backup_user_id')->nullable();
                }
                if (! Schema::hasColumn('validation_level_approvers', 'backup_role')) {
                    $table->string('backup_role', 64)->nullable();
                }
                if (! Schema::hasColumn('validation_level_approvers', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
            });
        }
    }

    public function down(): void {}
};
