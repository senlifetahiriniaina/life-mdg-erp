<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->enum('type', ['amazon', 'ebay']);
            $table->string('name');
            $table->json('config');
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_stats')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('company_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_channels');
    }
};
