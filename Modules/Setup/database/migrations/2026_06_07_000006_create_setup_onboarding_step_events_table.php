<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('setup_onboarding_step_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_session_id')->constrained('setup_onboarding_sessions')->cascadeOnDelete();
            $table->string('step_name', 32)->index();
            $table->string('event_type', 32)->index();
            $table->json('step_data')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_onboarding_step_events');
    }
};
