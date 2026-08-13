<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 200);
            $table->string('report_type', 30)->default('table');
            $table->string('data_source', 100);
            $table->json('query_config')->nullable();
            $table->json('filters')->nullable();
            $table->json('columns_config')->nullable();
            $table->json('sort_config')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
