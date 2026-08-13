<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if tables already exist (may be created by Module migrations)
        if (Schema::hasTable('hr_employees')) {
            return;
        }

        // hr_departments created WITHOUT manager_id FK (circular: manager is an hr_employee)
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreignId('parent_id')->nullable()->references('id')->on('hr_departments')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->references('id')->on('hr_departments')->nullOnDelete();
            $table->string('title');
            $table->string('level')->nullable();
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->references('id')->on('hr_departments')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->references('id')->on('hr_job_positions')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->references('id')->on('hr_employees')->nullOnDelete();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->string('national_id')->nullable();
            $table->string('passport_number')->nullable();
            $table->text('address')->nullable();
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('employment_type')->default('full_time');
            $table->string('status')->default('active');
            $table->string('avatar')->nullable();
            $table->json('emergency_contacts')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index('employee_number');
        });

        // Now add the deferred FK from hr_departments.manager_id → hr_employees.id
        if (!Schema::hasColumn('hr_departments', 'manager_id')) {
            Schema::table('hr_departments', function (Blueprint $table) {
                $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
            });
        }

        Schema::create('hr_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->integer('days_per_year')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('carry_forward')->default(false);
            $table->integer('max_carry_forward_days')->default(0);
            $table->timestamps();
        });

        Schema::create('hr_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->references('id')->on('hr_leave_types');
            $table->foreignId('approved_by')->nullable()->references('id')->on('hr_employees')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days')->default(1);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_leave_types');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_job_positions');
        Schema::table('hr_departments', function (Blueprint $table) {
            if (Schema::hasColumn('hr_departments', 'manager_id')) {
                try {
                    $table->dropForeign(['manager_id']);
                } catch (\Exception $e) {
                    // Foreign key may not exist
                }
            }
        });
        Schema::dropIfExists('hr_departments');
    }
};
