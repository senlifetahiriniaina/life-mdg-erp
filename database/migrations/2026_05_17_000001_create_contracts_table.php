<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->string('name')->index();
                $table->text('description')->nullable();
                $table->foreignId('customer_id')->constrained('users');
                $table->decimal('total_value', 15, 2);
                $table->decimal('monthly_value', 15, 2)->nullable();
                $table->decimal('price_per_unit', 15, 2)->nullable();
                $table->enum('revenue_type', ['milestone', 'time_based', 'usage_based', 'hybrid', 'deferred']);
                $table->dateTime('start_date');
                $table->dateTime('end_date');
                $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
                $table->decimal('recognized_revenue', 15, 2)->default(0);
                $table->decimal('deferred_revenue_balance', 15, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
