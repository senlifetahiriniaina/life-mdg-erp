<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 8.3 (HR): AttendanceBiometricController's 13 methods, AttendancePolicy,
 * and 6 real models (BiometricDevice, TimeOffRequest, AttendanceException,
 * AttendanceAnalytics, CompensationHistory, Deduction) were fully written but
 * never had a table at all — a real "table not found" landmine the moment this
 * controller is routed, not a hypothetical gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_biometric_devices')) {
            Schema::create('hr_biometric_devices', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->unique();
                $table->string('device_name');
                $table->string('device_type');
                $table->string('manufacturer')->nullable();
                $table->string('model')->nullable();
                $table->string('location');
                $table->string('building')->nullable();
                $table->string('floor')->nullable();
                $table->string('zone')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('ip_address')->nullable();
                $table->string('mac_address')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('last_sync')->nullable();
                $table->string('firmware_version')->nullable();
                $table->integer('capacity')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('hr_time_off_requests')) {
            Schema::create('hr_time_off_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('request_type');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('duration_days')->nullable();
                $table->decimal('duration_hours', 6, 2)->nullable();
                $table->string('status')->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('reason')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->boolean('partial_day')->default(false);
                $table->json('partial_day_details')->nullable();
                $table->boolean('is_urgent')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
                $table->index(['employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('hr_attendance_exceptions')) {
            Schema::create('hr_attendance_exceptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('attendance_date');
                $table->string('exception_type');
                $table->integer('minutes_late')->nullable();
                $table->integer('minutes_early')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('flagged');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('manager_notes')->nullable();
                $table->text('employee_response')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
                $table->index(['employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('hr_attendance_analytics')) {
            Schema::create('hr_attendance_analytics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->date('analytics_date');
                $table->unsignedTinyInteger('month')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->integer('days_present')->default(0);
                $table->integer('days_absent')->default(0);
                $table->integer('days_late')->default(0);
                $table->integer('days_early_departure')->default(0);
                $table->integer('total_late_minutes')->default(0);
                $table->integer('total_early_minutes')->default(0);
                $table->integer('total_working_minutes')->default(0);
                $table->integer('total_expected_minutes')->default(0);
                $table->decimal('attendance_percentage', 5, 2)->nullable();
                $table->decimal('punctuality_percentage', 5, 2)->nullable();
                $table->string('trend')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
                $table->index(['employee_id', 'year', 'month']);
            });
        }

        if (! Schema::hasTable('hr_compensation_history')) {
            Schema::create('hr_compensation_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('change_type');
                $table->decimal('previous_amount', 14, 2)->nullable();
                $table->decimal('new_amount', 14, 2)->nullable();
                $table->decimal('amount_difference', 14, 2)->nullable();
                $table->string('currency', 3)->default('MGA');
                $table->date('effective_date');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
                $table->index(['employee_id', 'effective_date']);
            });
        }

        if (! Schema::hasTable('hr_deductions')) {
            Schema::create('hr_deductions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->string('name');
                $table->string('type');
                $table->string('category')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('frequency')->default('monthly');
                $table->decimal('limit', 14, 2)->nullable();
                $table->decimal('ytd_amount', 14, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
                $table->index(['employee_id', 'is_active']);
            });
        }

        // hr_attendance_records exists (scaffold + earlier patches) but is still
        // missing columns the real AttendanceRecord model/AttendanceBiometricController
        // actively read/write: break_minutes/notes/location_lat/location_lng (model
        // $fillable), plus device_id/clock_in_method/device_name/verification_status
        // (clockIn()/listAttendance() — silently dropped on every create() today
        // since none of these are in the model's $fillable).
        Schema::table('hr_attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance_records', 'break_minutes')) {
                $table->integer('break_minutes')->default(0)->after('clock_out');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'notes')) {
                $table->text('notes')->nullable()->after('break_minutes');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'location_lat')) {
                $table->decimal('location_lat', 10, 7)->nullable()->after('location');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'location_lng')) {
                $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'device_id')) {
                $table->unsignedBigInteger('device_id')->nullable()->after('employee_id');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'clock_in_method')) {
                $table->string('clock_in_method')->nullable()->after('device_id');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'device_name')) {
                $table->string('device_name')->nullable()->after('clock_in_method');
            }
            if (! Schema::hasColumn('hr_attendance_records', 'verification_status')) {
                $table->string('verification_status')->nullable()->after('device_name');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_deductions');
        Schema::dropIfExists('hr_compensation_history');
        Schema::dropIfExists('hr_attendance_analytics');
        Schema::dropIfExists('hr_attendance_exceptions');
        Schema::dropIfExists('hr_time_off_requests');
        Schema::dropIfExists('hr_biometric_devices');
    }
};
