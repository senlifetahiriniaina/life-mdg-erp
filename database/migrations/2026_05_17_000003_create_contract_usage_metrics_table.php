<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_usage_metrics')) {
            Schema::create('contract_usage_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
                $table->string('type')->index(); // 'api_calls', 'storage_gb', 'users', etc.
                $table->unsignedBigInteger('quantity');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('revenue_amount', 15, 2);
                $table->dateTime('recorded_at')->index();
                $table->dateTime('revenue_recognized_date')->nullable();
                $table->string('reference')->nullable(); // External reference (transaction ID, etc.)
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_usage_metrics');
    }
};
