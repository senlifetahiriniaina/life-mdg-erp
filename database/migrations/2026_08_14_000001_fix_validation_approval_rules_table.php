<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// validation_approval_rules was only ever created as a generic stub table
// (id, tenant_id, status, data, timestamps) — this adds the real columns the
// ApprovalRule model has always declared in $fillable, so creating a rule with
// its documented fields no longer throws an "Unknown column" SQL error.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_approval_rules')) {
            Schema::table('validation_approval_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_rules', 'workflow_id')) {
                    $table->unsignedBigInteger('workflow_id')->nullable()->index();
                }
                if (! Schema::hasColumn('validation_approval_rules', 'rule_order')) {
                    $table->integer('rule_order')->default(1);
                }
                if (! Schema::hasColumn('validation_approval_rules', 'condition_type')) {
                    $table->string('condition_type', 64)->nullable();
                }
                if (! Schema::hasColumn('validation_approval_rules', 'condition_operator')) {
                    $table->string('condition_operator', 8)->nullable();
                }
                if (! Schema::hasColumn('validation_approval_rules', 'condition_value')) {
                    $table->string('condition_value', 255)->nullable();
                }
                if (! Schema::hasColumn('validation_approval_rules', 'condition_field')) {
                    // Which attribute path to read off the approvable model when
                    // condition_type = 'custom_field' (e.g. 'metadata.priority').
                    $table->string('condition_field', 128)->nullable();
                }
                if (! Schema::hasColumn('validation_approval_rules', 'required_approvers_count')) {
                    $table->integer('required_approvers_count')->default(1);
                }
                if (! Schema::hasColumn('validation_approval_rules', 'approval_mode')) {
                    $table->string('approval_mode', 32)->default('sequential');
                }
                if (! Schema::hasColumn('validation_approval_rules', 'hierarchy_id')) {
                    $table->unsignedBigInteger('hierarchy_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void {}
};
