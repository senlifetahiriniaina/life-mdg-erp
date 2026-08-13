<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_departments', 'budget_allocation')) {
                $table->decimal('budget_allocation', 15, 2)->nullable()->after('description');
            }
            if (! Schema::hasColumn('hr_departments', 'status')) {
                $table->string('status')->default('active')->after('budget_allocation');
            }
            if (! Schema::hasColumn('hr_departments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employees', 'base_salary')) {
                $table->decimal('base_salary', 15, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('hr_employees', 'salary_currency')) {
                $table->string('salary_currency', 3)->default('XOF')->after('base_salary');
            }
            if (! Schema::hasColumn('hr_employees', 'country_code')) {
                $table->string('country_code', 2)->nullable()->after('salary_currency');
            }
            if (! Schema::hasColumn('hr_employees', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }
        });

        if (! Schema::hasTable('hr_timesheets')) {
            Schema::create('hr_timesheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->date('date');
                $table->decimal('hours_worked', 5, 2)->default(0);
                $table->decimal('overtime_hours', 5, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_employee_loans')) {
            Schema::create('hr_employee_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->decimal('monthly_installment', 15, 2);
                $table->string('status')->default('active');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropColumnIfExists('budget_allocation');
            $table->dropColumnIfExists('status');
        });
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumnIfExists('base_salary');
            $table->dropColumnIfExists('salary_currency');
            $table->dropColumnIfExists('country_code');
        });
    }
};
