<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_financial_simulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('granularity', 10)->default('month'); // week|month
            $table->date('start_date');
            $table->unsignedSmallInteger('horizon_periods')->default(12);
            $table->decimal('opening_cash_balance', 18, 2)->nullable();
            $table->string('status', 20)->default('draft'); // draft|active|archived
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_financial_simulations');
    }
};
