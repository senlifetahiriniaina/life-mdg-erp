<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_payroll_records')) {
            Schema::create('hr_payroll_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->unsignedBigInteger('payroll_config_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_salary', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('net_salary', 15, 2)->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->json('breakdown')->nullable();
                $table->string('status', 20)->default('draft');
                $table->date('payment_date')->nullable();
                $table->timestamps();
                $table->index(['employee_id', 'period_start'], 'idx_payroll_employee_period');
            });
        } else {
            Schema::table('hr_payroll_records', function (Blueprint $table) {
                if (! Schema::hasColumn('hr_payroll_records', 'period_start')) {
                    $col = $table->date('period_start')->nullable();
                    if (Schema::hasColumn('hr_payroll_records', 'payroll_config_id')) { $col->after('payroll_config_id'); }
                }
                if (! Schema::hasColumn('hr_payroll_records', 'period_end')) {
                    $col = $table->date('period_end')->nullable();
                    if (Schema::hasColumn('hr_payroll_records', 'period_start')) { $col->after('period_start'); }
                }
                if (! Schema::hasColumn('hr_payroll_records', 'total_deductions')) {
                    $col = $table->decimal('total_deductions', 15, 2)->default(0);
                    if (Schema::hasColumn('hr_payroll_records', 'gross_salary')) { $col->after('gross_salary'); }
                }
                if (! Schema::hasColumn('hr_payroll_records', 'currency')) {
                    $col = $table->string('currency', 3)->default('XOF');
                    if (Schema::hasColumn('hr_payroll_records', 'net_salary')) { $col->after('net_salary'); }
                }
                if (! Schema::hasColumn('hr_payroll_records', 'breakdown')) {
                    $col = $table->json('breakdown')->nullable();
                    if (Schema::hasColumn('hr_payroll_records', 'currency')) { $col->after('currency'); }
                }
                if (! Schema::hasColumn('hr_payroll_records', 'payroll_config_id')) {
                    $table->unsignedBigInteger('payroll_config_id')->nullable()->after('employee_id');
                }
                if (! Schema::hasColumn('hr_payroll_records', 'payment_date')) {
                    $table->date('payment_date')->nullable();
                }
            });
        }

        if (! Schema::hasTable('hr_payroll_periods')) {
            Schema::create('hr_payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('draft'); // draft|processing|approved|paid|cancelled
                $table->date('pay_date')->nullable();
                $table->decimal('total_gross', 15, 4)->default(0);
                $table->decimal('total_deductions', 15, 4)->default(0);
                $table->decimal('total_net', 15, 4)->default(0);
                $table->unsignedInteger('employee_count')->default(0);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_payslips')) {
            Schema::create('hr_payslips', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_period_id')->nullable()->index();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('status', 20)->default('draft'); // draft|approved|paid
                $table->decimal('base_salary', 15, 4)->default(0);
                $table->decimal('gross_salary', 15, 4)->default(0);
                $table->decimal('total_deductions', 15, 4)->default(0);
                $table->decimal('net_salary', 15, 4)->default(0);
                $table->decimal('worked_days', 5, 2)->default(0);
                $table->decimal('leave_days', 5, 2)->default(0);
                $table->decimal('overtime_hours', 6, 2)->default(0);
                $table->decimal('overtime_amount', 15, 4)->default(0);
                $table->text('notes')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('hr_payslip_lines')) {
            Schema::create('hr_payslip_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payslip_id')->index();
                $table->string('type', 20); // earning|deduction
                $table->string('label');
                $table->decimal('amount', 15, 4)->default(0);
                $table->string('code')->nullable(); // OHADA account code
                $table->timestamps();

                $table->foreign('payslip_id')->references('id')->on('hr_payslips')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payslip_lines');
        Schema::dropIfExists('hr_payslips');
        Schema::dropIfExists('hr_payroll_periods');
    }
};
