<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Modules\HR\Models\LeaveBalance` has pointed at `hr_leave_balances` since
 * its creation, but no migration ever created it — only flat
 * annual_leave_balance/sick_leave_balance columns exist on hr_employees.
 * Columns derived from the model's own $fillable/$casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_leave_balances')) {
            Schema::create('hr_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')
                    ->constrained('hr_employees')
                    ->cascadeOnDelete();
                $table->string('leave_type');
                $table->decimal('accrual_days_per_year', 8, 2)->default(0);
                $table->decimal('balance', 8, 2)->default(0);
                $table->decimal('used', 8, 2)->default(0);
                $table->decimal('pending', 8, 2)->default(0);
                $table->unsignedSmallInteger('year');
                $table->date('reset_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['employee_id', 'leave_type', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_balances');
    }
};
