<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_trust_zones', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('zone_name', 128);
            $table->string('trust_level', 16)->default('medium')->index();
            $table->json('ip_ranges')->nullable();
            $table->json('allowed_services')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_trust_zones');
    }
};
