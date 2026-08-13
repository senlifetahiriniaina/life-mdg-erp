<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_lead_status_logs')) {
            return;
        }

        Schema::create('crm_lead_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->nullable()->index();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->text('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_status_logs');
    }
};
