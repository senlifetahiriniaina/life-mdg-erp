<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects_saved_views', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');              // task | project | milestone
            $table->string('name');
            $table->json('filters')->nullable();        // [{field, op, value}, ...]
            $table->string('sort_by')->nullable();
            $table->string('sort_direction', 4)->default('asc'); // asc | desc
            $table->string('group_by')->nullable();
            $table->json('visible_columns')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['entity_type', 'created_by']);
            $table->index(['entity_type', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects_saved_views');
    }
};
