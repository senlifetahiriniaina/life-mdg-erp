<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('hr_employee_compensation')) {
            Schema::create('hr_employee_compensation', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->decimal('base_salary', 15, 2)->nullable();
                $table->string('currency', 10)->default('XOF');
                $table->decimal('bonus_amount', 15, 2)->nullable();
                $table->string('bonus_frequency', 20)->nullable();
                $table->decimal('equity_granted', 15, 4)->nullable();
                $table->unsignedInteger('equity_vesting_period_months')->nullable();
                $table->json('equity_vesting_schedule')->nullable();
                $table->decimal('equity_vested_percentage', 5, 2)->nullable();
                $table->decimal('benefits_annual_value', 15, 2)->nullable();
                $table->decimal('total_compensation', 15, 2)->nullable();
                $table->date('effective_date');
                $table->date('end_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['employee_id', 'effective_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_compensation');
    }
};
