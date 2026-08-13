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
        // ── wa_conversations ──────────────────────────────────────────────
        $this->patch('wa_conversations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_ai_handled'))  $t->boolean('is_ai_handled')->default(false);
            if (!Schema::hasColumn($table, 'assigned_to'))    $t->unsignedBigInteger('assigned_to')->nullable();
            if (!Schema::hasColumn($table, 'last_message_at')) $t->timestamp('last_message_at')->nullable();
        });

        // ── hr_review_cycles (more columns) ───────────────────────────────
        $this->patch('hr_review_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'self_review_enabled')) $t->boolean('self_review_enabled')->default(false);
            if (!Schema::hasColumn($table, 'peer_review_enabled')) $t->boolean('peer_review_enabled')->default(false);
            if (!Schema::hasColumn($table, 'due_date'))            $t->date('due_date')->nullable();
            if (!Schema::hasColumn($table, 'notes'))               $t->text('notes')->nullable();
            if (!Schema::hasColumn($table, 'created_by'))          $t->unsignedBigInteger('created_by')->nullable();
        });

        // ── hr_appraisal_cycles (more columns) ────────────────────────────
        $this->patch('hr_appraisal_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'review_deadline'))               $t->date('review_deadline')->nullable();
            if (!Schema::hasColumn($table, 'self_assessment_deadline'))      $t->date('self_assessment_deadline')->nullable();
        });

        // ── hr_attendance_records (more columns) ──────────────────────────
        $this->patch('hr_attendance_records', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'clock_in'))   $t->timestamp('clock_in')->nullable();
            if (!Schema::hasColumn($table, 'clock_out'))  $t->timestamp('clock_out')->nullable();
            if (!Schema::hasColumn($table, 'type'))       $t->string('type', 20)->default('regular');
            if (!Schema::hasColumn($table, 'ip_address')) $t->string('ip_address')->nullable();
            if (!Schema::hasColumn($table, 'location'))   $t->string('location')->nullable();
        });

        // ── hr_succession_plans (more fields) ─────────────────────────────
        $this->patch('hr_succession_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'review_date'))  $t->date('review_date')->nullable();
            if (!Schema::hasColumn($table, 'reviewed_by'))  $t->unsignedBigInteger('reviewed_by')->nullable();
        });

        // ── hr_training_courses (more fields) ─────────────────────────────
        $this->patch('hr_training_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))      $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
        });
    }

    public function down(): void {}
};
