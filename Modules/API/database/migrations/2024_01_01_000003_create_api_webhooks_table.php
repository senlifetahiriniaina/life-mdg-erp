<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('api_webhooks')) {
            Schema::create('api_webhooks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('name');
                $table->string('url', 1000);
                $table->json('events')->nullable();
                $table->string('secret', 64)->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamp('last_triggered_at')->nullable();
                $table->unsignedSmallInteger('last_status_code')->nullable();
                $table->unsignedInteger('failure_count')->default(0);
                $table->json('headers')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_webhooks');
    }
};
