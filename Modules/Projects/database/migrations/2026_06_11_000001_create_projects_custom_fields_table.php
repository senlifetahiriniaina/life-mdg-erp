<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');                 // task | project | milestone
            $table->string('field_name');                  // snake_case machine key
            $table->string('field_label');                 // Human-readable label
            $table->string('field_type', 20);              // text | number | date | select | multiselect | checkbox
            $table->json('options')->nullable();            // For select/multiselect
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('entity_type');
            $table->index(['entity_type', 'sort_order']);
            // Unique field_name per entity_type (soft-deletes aware — only active records)
            $table->unique(['entity_type', 'field_name', 'deleted_at'], 'pcf_entity_name_unique');
        });

        Schema::create('projects_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('custom_field_id');
            $table->string('entity_type');                 // task | project | milestone
            $table->unsignedBigInteger('entity_id');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->foreign('custom_field_id')
                ->references('id')
                ->on('projects_custom_fields')
                ->cascadeOnDelete();

            $table->index(['entity_type', 'entity_id']);
            $table->unique(['custom_field_id', 'entity_type', 'entity_id'], 'pcfv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_custom_field_values');
        Schema::dropIfExists('projects_custom_fields');
    }
};
