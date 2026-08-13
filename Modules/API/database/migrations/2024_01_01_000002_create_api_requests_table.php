<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_requests')) {
            Schema::create('api_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('api_key_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('method', 10);
                $table->string('endpoint', 500);
                $table->json('query_params')->nullable();
                $table->json('request_body')->nullable();
                $table->json('response_body')->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->nullable()->index();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['api_key_id', 'created_at']);
                $table->index('status_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
