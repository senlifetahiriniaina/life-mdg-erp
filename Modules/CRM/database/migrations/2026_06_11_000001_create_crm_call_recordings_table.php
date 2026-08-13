<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_call_recordings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('call_id');
            $table->string('recording_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->json('ai_summary')->nullable();
            $table->enum('status', ['recording', 'processing', 'ready'])->default('recording');
            $table->unsignedBigInteger('company_id');
            $table->timestamps();

            $table->foreign('call_id')
                ->references('id')
                ->on('crm_call_logs')
                ->onDelete('cascade');

            $table->index('call_id');
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_call_recordings');
    }
};
