<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_compliance_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_control_id')->constrained('security_compliance_controls')->cascadeOnDelete();
            $table->string('violation_type', 64);
            $table->text('description');
            $table->string('severity', 16)->default('medium')->index();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_compliance_violations');
    }
};
