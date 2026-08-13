<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (false) { // 'notes_pages' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('notes_pages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('title');
                $table->longText('content')->nullable();
                $table->string('icon', 10)->nullable()->default('📄');
                $table->string('cover')->nullable();
                $table->string('visibility', 20)->default('private'); // private|shared
                $table->boolean('is_favorite')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('template_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('parent_id')->references('id')->on('notes_pages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_pages');
    }
};
