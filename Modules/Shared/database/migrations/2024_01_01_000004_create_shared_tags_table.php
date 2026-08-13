<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_tags')) {
            Schema::create('shared_tags', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 100);
                $table->string('slug', 100)->index();
                $table->string('color', 20)->default('#6366f1');
                $table->string('icon', 50)->nullable();
                $table->string('module', 100)->nullable()->index();
                $table->text('description')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug', 'module']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_tags');
    }
};
