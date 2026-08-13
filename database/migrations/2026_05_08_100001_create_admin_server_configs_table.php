<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_server_configs')) {
            return;
        }

        Schema::create('admin_server_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('provider', ['gcp', 'aws', 'azure', 'digitalocean', 'hetzner', 'ovh', 'custom']);
            $table->string('region')->default('');
            $table->string('instance_type')->default('');
            $table->string('ip_address')->nullable();
            $table->enum('status', ['active', 'stopped', 'maintenance', 'unknown'])->default('unknown');
            $table->text('credentials_encrypted')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_server_configs');
    }
};
