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
        // ── mfg_work_orders (more columns) ────────────────────────────────
        $this->patch('mfg_work_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_planned'))   $t->decimal('quantity_planned', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'quantity_produced'))  $t->decimal('quantity_produced', 15, 4)->default(0);
        });

        // ── mfg_production_orders (more columns) ──────────────────────────
        $this->patch('mfg_production_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes'))  $t->text('notes')->nullable();
        });

        // ── mfg_bom_lines (more columns) ──────────────────────────────────
        $this->patch('mfg_bom_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'operation'))  $t->string('operation')->nullable();
        });

        // ── mfg_routings (more columns) ───────────────────────────────────
        $this->patch('mfg_routings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'workcenter_id'))  $t->unsignedBigInteger('workcenter_id')->nullable();
        });

        // ── mfg_subcontractors (more columns) ─────────────────────────────
        $this->patch('mfg_subcontractors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_email'))  $t->string('contact_email')->nullable();
        });

        // ── mfg_capacity_constraints (more columns) ───────────────────────
        $this->patch('mfg_capacity_constraints', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'available_hours'))  $t->decimal('available_hours', 8, 2)->nullable();
        });

        // ── inventory_lots (more columns) ─────────────────────────────────
        $this->patch('inventory_lots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))  $t->unsignedBigInteger('warehouse_id')->nullable();
        });

        // ── inventory_transfer_orders (more columns) ──────────────────────
        $this->patch('inventory_transfer_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'priority'))  $t->string('priority', 10)->default('normal');
        });

        // ── inventory_valuation_runs (more columns) ────────────────────────
        $this->patch('inventory_valuation_runs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'valuation_date'))  $t->date('valuation_date')->nullable();
        });

        // ── inventory_shipment_events ─────────────────────────────────────
        $this->patch('inventory_shipment_events', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipment_id'))   $t->unsignedBigInteger('shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'event_type'))    $t->string('event_type', 30)->nullable();
            if (!Schema::hasColumn($table, 'description'))   $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'occurred_at'))   $t->timestamp('occurred_at')->nullable();
            if (!Schema::hasColumn($table, 'location'))      $t->string('location')->nullable();
        });

        // ── inventory_picking_orders (more columns) ────────────────────────
        $this->patch('inventory_picking_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'priority'))  $t->string('priority', 10)->default('normal');
        });

        // ── inventory_rmas (more columns) ─────────────────────────────────
        $this->patch('inventory_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approved_at'))  $t->timestamp('approved_at')->nullable();
        });

        // ── logistics_locations (more columns) ────────────────────────────
        $this->patch('logistics_locations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'parent_id'))  $t->unsignedBigInteger('parent_id')->nullable();
        });

        // ── logistics_customs_declarations ────────────────────────────────
        $this->patch('logistics_customs_declarations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference'))     $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'shipment_id'))   $t->unsignedBigInteger('shipment_id')->nullable();
            if (!Schema::hasColumn($table, 'country'))       $t->string('country', 10)->nullable();
            if (!Schema::hasColumn($table, 'hs_codes'))      $t->text('hs_codes')->nullable();
            if (!Schema::hasColumn($table, 'duties_amount')) $t->decimal('duties_amount', 15, 4)->default(0);
        });

        // ── hr_candidates (more columns) ──────────────────────────────────
        $this->patch('hr_candidates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'job_posting_id'))  $t->unsignedBigInteger('job_posting_id')->nullable();
        });

        // ── hr_performance_reviews (more columns) ─────────────────────────
        $this->patch('hr_performance_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'review_type'))  $t->string('review_type', 20)->default('annual');
        });

        // ── hr_critical_positions (more columns) ──────────────────────────
        $this->patch('hr_critical_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'impact_description'))  $t->text('impact_description')->nullable();
        });

        // ── hr_appraisals ─────────────────────────────────────────────────
        $this->patch('hr_appraisals', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'overall_rating'))  $t->decimal('overall_rating', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'employee_id'))     $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'cycle_id'))        $t->unsignedBigInteger('cycle_id')->nullable();
            if (!Schema::hasColumn($table, 'reviewer_id'))     $t->unsignedBigInteger('reviewer_id')->nullable();
            if (!Schema::hasColumn($table, 'submitted_at'))    $t->timestamp('submitted_at')->nullable();
        });

        // ── hr_attendance (more columns) ──────────────────────────────────
        $this->patch('hr_attendance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'check_in_time'))   $t->time('check_in_time')->nullable();
            if (!Schema::hasColumn($table, 'check_out_time'))  $t->time('check_out_time')->nullable();
        });

        // ── hr_courses (more columns) ─────────────────────────────────────
        $this->patch('hr_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'level'))  $t->string('level', 20)->default('beginner');
        });
    }

    public function down(): void {}
};
