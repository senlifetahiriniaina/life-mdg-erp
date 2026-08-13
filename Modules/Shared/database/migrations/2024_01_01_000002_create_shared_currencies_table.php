<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_currencies')) {
            Schema::create('shared_currencies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 3)->unique();
                $table->string('name', 100);
                $table->string('name_fr', 100)->nullable();
                $table->string('symbol', 10);
                $table->string('symbol_native', 10)->nullable();
                $table->unsignedTinyInteger('decimals')->default(2);
                $table->boolean('is_cfa')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->decimal('exchange_rate_to_usd', 18, 6)->nullable();
                $table->timestamp('exchange_rate_updated_at')->nullable();
                $table->string('region', 50)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_currencies');
    }
};
