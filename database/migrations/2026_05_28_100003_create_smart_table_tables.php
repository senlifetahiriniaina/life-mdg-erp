<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (false) { // 'smart_bases' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('smart_bases', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('name', 100);
                $table->string('description')->nullable();
                $table->string('icon', 10)->nullable()->default('🗂️');
                $table->string('color', 7)->nullable();
                $table->boolean('is_shared')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (false) { // 'smart_tables' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('smart_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('base_id')->index();
                $table->string('name', 100);
                $table->string('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('base_id')->references('id')->on('smart_bases')->cascadeOnDelete();
            });
        }

        if (false) { // 'smart_columns' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('smart_columns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('table_id')->index();
                $table->string('name', 100);
                $table->string('type', 30)->default('text'); // text|number|date|select|multiselect|checkbox|url|email|phone|relation
                $table->json('options')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedSmallInteger('width')->default(200);
                $table->timestamps();

                $table->foreign('table_id')->references('id')->on('smart_tables')->cascadeOnDelete();
            });
        }

        if (false) { // 'smart_rows' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('smart_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('table_id')->index();
                $table->json('data')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('table_id')->references('id')->on('smart_tables')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_rows');
        Schema::dropIfExists('smart_columns');
        Schema::dropIfExists('smart_tables');
        Schema::dropIfExists('smart_bases');
    }
};
