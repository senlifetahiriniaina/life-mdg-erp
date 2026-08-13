<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->string('provider', 50);        // marinetraffic|flexport|flightaware|fallback|ocean|air
            $table->json('raw_payload')->nullable(); // Raw provider response
            $table->string('event_type', 50);       // departed|arrived|transshipment|in_transit|update
            $table->timestamp('event_at');
            $table->string('location', 20)->nullable(); // LOCODE or IATA code
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('provider');
            $table->index('event_at');
            $table->index(['shipment_id', 'provider', 'event_type', 'event_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
