<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('core_custom_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('core_custom_fields', 'field_key'))
                $table->string('field_key')->nullable();
            if (!Schema::hasColumn('core_custom_fields', 'field_label'))
                $table->string('field_label')->nullable();
            if (!Schema::hasColumn('core_custom_fields', 'field_type'))
                $table->string('field_type', 30)->default('text');
            if (!Schema::hasColumn('core_custom_fields', 'is_unique'))
                $table->boolean('is_unique')->default(false);
            if (!Schema::hasColumn('core_custom_fields', 'is_searchable'))
                $table->boolean('is_searchable')->default(false);
            if (!Schema::hasColumn('core_custom_fields', 'default_value'))
                $table->string('default_value')->nullable();
            if (!Schema::hasColumn('core_custom_fields', 'validation_rules'))
                $table->text('validation_rules')->nullable();
            if (!Schema::hasColumn('core_custom_fields', 'group_name'))
                $table->string('group_name')->nullable();
            if (!Schema::hasColumn('core_custom_fields', 'sort_order'))
                $table->integer('sort_order')->default(0);
            if (!Schema::hasColumn('core_custom_fields', 'is_active'))
                $table->boolean('is_active')->default(true);
        });

        Schema::table('core_custom_field_values', function (Blueprint $table) {
            if (!Schema::hasColumn('core_custom_field_values', 'custom_field_id'))
                $table->unsignedBigInteger('custom_field_id')->nullable();
            if (!Schema::hasColumn('core_custom_field_values', 'entity_type'))
                $table->string('entity_type')->nullable();
            if (!Schema::hasColumn('core_custom_field_values', 'entity_id'))
                $table->unsignedBigInteger('entity_id')->nullable();
            if (!Schema::hasColumn('core_custom_field_values', 'value'))
                $table->text('value')->nullable();
        });
    }

    public function down(): void {}
};
