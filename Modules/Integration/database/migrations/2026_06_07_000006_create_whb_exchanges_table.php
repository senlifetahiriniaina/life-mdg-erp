<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whb_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('whb_connections')->cascadeOnDelete();
            $table->string('exchange_type', 32)->index();
            $table->string('direction', 8);
            $table->string('status', 16)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whb_exchanges');
    }
};
