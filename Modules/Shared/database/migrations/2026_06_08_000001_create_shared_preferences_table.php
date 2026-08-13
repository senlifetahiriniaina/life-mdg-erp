<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('preference_key', 200);
            $table->text('preference_value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'preference_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_preferences');
    }
};
