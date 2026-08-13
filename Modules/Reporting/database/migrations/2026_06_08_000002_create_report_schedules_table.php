<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('report_id');
            $table->string('name', 200)->nullable();
            $table->string('cron_expression', 100);
            $table->json('recipients');
            $table->string('format', 20)->default('pdf');
            $table->string('locale', 10)->default('fr');
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);

            $table->foreign('report_id')
                ->references('id')
                ->on('report_definitions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
