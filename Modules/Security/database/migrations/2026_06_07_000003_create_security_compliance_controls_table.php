<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_compliance_controls', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('framework', 64)->index();
            $table->string('control_id', 32);
            $table->string('control_name', 128);
            $table->text('control_description')->nullable();
            $table->string('control_type', 32);
            $table->string('implementation_status', 32)->default('not_started')->index();
            $table->json('implementation_details')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_compliance_controls');
    }
};
