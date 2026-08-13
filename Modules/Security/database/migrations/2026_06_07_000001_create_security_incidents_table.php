<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('incident_type', 64)->index();
            $table->string('severity', 16)->default('medium');
            $table->text('description');
            $table->json('threat_indicators')->nullable();
            $table->string('incident_status', 32)->default('open')->index();
            $table->timestamp('detected_at');
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('affected_resources')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
