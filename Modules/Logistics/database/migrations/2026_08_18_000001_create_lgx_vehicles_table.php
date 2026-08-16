<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lgx_vehicles')) {
            Schema::create('lgx_vehicles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('name');
                $table->string('plate_number')->nullable();
                $table->string('type')->nullable();
                $table->decimal('max_weight_kg', 10, 2)->nullable();
                $table->decimal('max_volume_m3', 10, 3)->nullable();
                $table->string('status')->default('active');
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->string('fuel_type')->nullable();
                $table->decimal('fuel_consumption_per_100km', 8, 2)->nullable();
                $table->timestamps();

                $table->index('company_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lgx_vehicles');
    }
};
