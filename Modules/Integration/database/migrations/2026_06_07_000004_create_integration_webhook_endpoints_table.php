<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->nullable()->constrained('integration_connectors')->nullOnDelete();
            $table->string('url', 512);
            $table->string('method', 8)->default('POST');
            $table->json('headers')->nullable();
            $table->string('secret_key', 128)->nullable();
            $table->unsignedTinyInteger('retry_attempts')->default(3);
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_webhook_endpoints');
    }
};
