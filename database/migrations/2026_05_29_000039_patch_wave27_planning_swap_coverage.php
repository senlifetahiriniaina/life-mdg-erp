<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        $this->patch('planning_shift_swap_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approved_by'))      $t->unsignedBigInteger('approved_by')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))      $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'approval_notes'))   $t->text('approval_notes')->nullable();
        });

        $this->patch('planning_shift_coverage_requests', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'assigned_at'))      $t->timestamp('assigned_at')->nullable();
            if (!Schema::hasColumn($table, 'schedule_id'))      $t->unsignedBigInteger('schedule_id')->nullable();
            if (!Schema::hasColumn($table, 'requested_by'))     $t->unsignedBigInteger('requested_by')->nullable();
            if (!Schema::hasColumn($table, 'description'))      $t->text('description')->nullable();
        });

        // ── Also fix remaining Planning table issues ───────────────────────
        $this->patch('planning_schedule_templates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))             $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'type'))             $t->string('type', 30)->default('weekly');
            if (!Schema::hasColumn($table, 'department_id'))    $t->unsignedBigInteger('department_id')->nullable();
            if (!Schema::hasColumn($table, 'config'))           $t->text('config')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))        $t->boolean('is_active')->default(true);
        });

        $this->patch('planning_employee_preferences', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))      $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'preferred_shifts')) $t->text('preferred_shifts')->nullable();
            if (!Schema::hasColumn($table, 'max_hours_week'))   $t->integer('max_hours_week')->default(40);
            if (!Schema::hasColumn($table, 'unavailable_days')) $t->text('unavailable_days')->nullable();
        });
    }

    public function down(): void {}
};
