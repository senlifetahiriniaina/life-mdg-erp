<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_threat_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_type', 64)->index();
            $table->string('indicator_value', 512);
            $table->string('threat_level', 16)->index();
            $table->text('description')->nullable();
            $table->string('source', 128)->nullable();
            $table->boolean('is_whitelisted')->default(false)->index();
            $table->timestamp('detected_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_threat_indicators');
    }
};
