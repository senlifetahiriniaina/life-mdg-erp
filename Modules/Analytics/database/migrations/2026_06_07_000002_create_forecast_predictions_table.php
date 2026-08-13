<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('forecast_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_model_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date')->index();
            $table->decimal('predicted_value', 18, 4);
            $table->decimal('lower_bound', 18, 4)->nullable();
            $table->decimal('upper_bound', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('error_rate', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['forecast_model_id', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_predictions');
    }
};
