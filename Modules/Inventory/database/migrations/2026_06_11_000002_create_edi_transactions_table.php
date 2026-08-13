<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edi_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);           // 850, 856, 810, unknown
            $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
            $table->longText('content_raw');
            $table->json('parsed_json')->nullable();
            $table->string('status', 20)->default('received'); // received|processed|error
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index('type');
            $table->index('direction');
            $table->index('status');
            $table->index('occurred_at');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edi_transactions');
    }
};
