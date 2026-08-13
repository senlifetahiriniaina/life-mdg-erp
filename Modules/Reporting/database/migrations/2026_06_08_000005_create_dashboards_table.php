<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
