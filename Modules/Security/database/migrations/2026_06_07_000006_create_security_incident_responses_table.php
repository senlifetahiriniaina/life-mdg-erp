<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_incident_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_incident_id')->constrained('security_incidents')->cascadeOnDelete();
            $table->string('response_type', 64);
            $table->string('response_status', 32)->default('pending')->index();
            $table->json('response_config')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_responses');
    }
};
