<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Modules\HR\Models\ShiftSchedule has always pointed at hr_shift_schedules
// and has a full working API (isActive(), getWorksOnDay(), getDurationHours())
// but the table itself was never created — no admin has ever been able to
// configure anyone's working hours. This creates it, matching the model's
// $fillable/$casts exactly. It starts empty: ApprovalRoutingResolver's
// isAvailable() check must treat "no ShiftSchedule row for this employee" as
// available (fail-open), not unavailable — otherwise every approval would
// auto-escalate the moment this feature ships, before any shift is ever
// configured.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_shift_schedules')) {
            Schema::create('hr_shift_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id')->index();
                $table->string('shift_name')->nullable();
                $table->string('shift_code')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->integer('working_hours')->nullable();
                $table->json('days_of_week')->nullable();
                $table->boolean('is_night_shift')->default(false);
                $table->boolean('is_flexible')->default(false);
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shift_schedules');
    }
};
