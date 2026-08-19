<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_financial_simulation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_simulation_id')->constrained('acc_financial_simulations')->cascadeOnDelete();
            $table->string('type', 10); // sale|purchase
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index(); // purchase lines only
            $table->unsignedBigInteger('contact_id')->nullable()->index(); // sale lines only, optional
            $table->string('label')->nullable();
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->string('recurrence', 10)->default('once'); // once|weekly|monthly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('growth_rate_percent', 8, 4)->default(0);
            $table->string('counterpart_account_code', 20)->nullable();
            $table->string('status', 10)->default('simulated'); // simulated|realized
            $table->timestamp('realized_at')->nullable();
            $table->string('realized_type')->nullable();
            $table->unsignedBigInteger('realized_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['realized_type', 'realized_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_financial_simulation_lines');
    }
};
