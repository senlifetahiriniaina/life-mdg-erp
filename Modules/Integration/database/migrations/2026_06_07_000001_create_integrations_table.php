<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('integration_key', 64)->unique();
            $table->string('name', 128);
            $table->string('status', 16)->default('inactive')->index();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedInteger('sync_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
