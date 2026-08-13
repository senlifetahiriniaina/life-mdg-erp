<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_modules')) {
            Schema::create('tenant_modules', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('module');
                $table->boolean('enabled')->default(true);
                $table->string('department')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'module', 'department']);
                $table->index(['tenant_id', 'enabled']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
