<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// validation_hierarchy_levels was only ever created as a generic stub table
// — this adds the real columns HierarchyLevel's model has always declared.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('validation_hierarchy_levels')) {
            Schema::table('validation_hierarchy_levels', function (Blueprint $table) {
                if (! Schema::hasColumn('validation_hierarchy_levels', 'hierarchy_id')) {
                    $table->unsignedBigInteger('hierarchy_id')->nullable()->index();
                }
                if (! Schema::hasColumn('validation_hierarchy_levels', 'level_order')) {
                    $table->integer('level_order')->default(1);
                }
                if (! Schema::hasColumn('validation_hierarchy_levels', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('validation_hierarchy_levels', 'approver_count')) {
                    $table->integer('approver_count')->default(1);
                }
                if (! Schema::hasColumn('validation_hierarchy_levels', 'delegation_allowed')) {
                    $table->boolean('delegation_allowed')->default(true);
                }
            });
        }
    }

    public function down(): void {}
};
