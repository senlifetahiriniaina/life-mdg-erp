<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 200);
            $table->text('sql_query');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->dateTime('last_executed_at')->nullable();
            $table->integer('execution_count')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['tenant_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_queries');
    }
};
