<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// validation_approval_hierarchies was only ever created as a generic stub table
// — this adds the real columns ApprovalHierarchy's model has always declared.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_approval_hierarchies')) {
            Schema::table('validation_approval_hierarchies', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_approval_hierarchies', 'name')) {
                    $table->string('name')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'module_name')) {
                    $table->string('module_name', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'escalation_role')) {
                    $table->string('escalation_role', 64)->nullable();
                }
                if (! Schema::hasColumn('validation_approval_hierarchies', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void {}
};
