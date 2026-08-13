<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->string('status', 30)->default('running');
            $table->integer('rows_count')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('format', 20)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'report_id']);

            $table->foreign('report_id')
                ->references('id')
                ->on('report_definitions')
                ->cascadeOnDelete();

            $table->foreign('schedule_id')
                ->references('id')
                ->on('report_schedules')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_executions');
    }
};
