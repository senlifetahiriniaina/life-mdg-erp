<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_authentication_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email', 191)->index();
            $table->string('event_type', 32)->index();
            $table->string('authentication_method', 32);
            $table->string('ip_address', 45)->index();
            $table->text('user_agent')->nullable();
            $table->json('device_info')->nullable();
            $table->string('status', 16)->index();
            $table->string('failure_reason', 128)->nullable();
            $table->decimal('trust_score', 5, 2)->nullable();
            $table->json('risk_factors')->nullable();
            $table->timestamp('authenticated_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_authentication_events');
    }
};
