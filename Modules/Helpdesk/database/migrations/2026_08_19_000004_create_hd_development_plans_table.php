<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual development plans for helpdesk agents — supervisor-authored
 * goals/focus-areas over a fixed duration, with milestone tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_development_plans')) {
            Schema::create('hd_development_plans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agent_id')->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->json('goals')->nullable();
                $table->json('focus_areas')->nullable();
                $table->unsignedSmallInteger('duration_months')->default(3);
                $table->json('milestones')->nullable();
                $table->unsignedSmallInteger('milestones_completed')->default(0);
                $table->string('status', 16)->default('active');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hd_development_plans');
    }
};
