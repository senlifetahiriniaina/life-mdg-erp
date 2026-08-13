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
            if (!Schema::hasColumn($table, 'bom_id'))  $t->unsignedBigInteger('bom_id')->nullable();
        });

        // ── mfg_production_orders (more columns) ──────────────────────────
        $this->patch('mfg_production_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'scheduled_date'))  $t->date('scheduled_date')->nullable();
            if (!Schema::hasColumn($table, 'bom_id'))          $t->unsignedBigInteger('bom_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))        $t->decimal('quantity', 15, 4)->default(1);
            if (!Schema::hasColumn($table, 'reference'))       $t->string('reference')->nullable();
        });

        // ── mfg_bom_lines (more columns) ──────────────────────────────────
        $this->patch('mfg_bom_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'scrap_percentage'))  $t->decimal('scrap_percentage', 5, 2)->default(0);
        });

        // ── mfg_routings (more columns) ───────────────────────────────────
        $this->patch('mfg_routings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bom_id'))  $t->unsignedBigInteger('bom_id')->nullable();
        });

        // ── mfg_mrp_runs (more columns) ───────────────────────────────────
        $this->patch('mfg_mrp_runs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'completed_at'))  $t->timestamp('completed_at')->nullable();
        });

        // ── mfg_subcontractors ────────────────────────────────────────────
        $this->patch('mfg_subcontractors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'email'))        $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'phone'))        $t->string('phone')->nullable();
            if (!Schema::hasColumn($table, 'country'))      $t->string('country', 10)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── inventory_lots (more columns) ─────────────────────────────────
        $this->patch('inventory_lots', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'manufacture_date'))  $t->date('manufacture_date')->nullable();
        });

        // ── inventory_transfer_orders (more columns) ──────────────────────
        $this->patch('inventory_transfer_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))  $t->string('type', 20)->default('internal');
        });

        // ── inventory_valuation_runs ──────────────────────────────────────
        $this->patch('inventory_valuation_runs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'method'))      $t->string('method', 20)->default('fifo');
            if (!Schema::hasColumn($table, 'run_date'))    $t->date('run_date')->nullable();
            if (!Schema::hasColumn($table, 'total_value')) $t->decimal('total_value', 15, 4)->default(0);
        });

        // ── logistics_locations ───────────────────────────────────────────
        $this->patch('logistics_locations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'warehouse_id'))  $t->unsignedBigInteger('warehouse_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))          $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'code'))          $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'type'))          $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))     $t->boolean('is_active')->default(true);
        });

        // ── hd_kb_articles (more columns) ─────────────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'excerpt'))  $t->text('excerpt')->nullable();
        });

        // ── hd_sla_breaches (more columns) ────────────────────────────────
        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'policy_id'))  $t->unsignedBigInteger('policy_id')->nullable();
        });

        // ── crm_ai_agents (more columns) ──────────────────────────────────
        $this->patch('crm_ai_agents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── crm_opportunity_scores ────────────────────────────────────────
        $this->patch('crm_opportunity_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'opportunity_id'))  $t->unsignedBigInteger('opportunity_id')->nullable();
            if (!Schema::hasColumn($table, 'score'))           $t->decimal('score', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'factors'))         $t->text('factors')->nullable();
            if (!Schema::hasColumn($table, 'scored_at'))       $t->timestamp('scored_at')->nullable();
        });

        // ── crm_sequence_steps ────────────────────────────────────────────
        $this->patch('crm_sequence_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sequence_id'))  $t->unsignedBigInteger('sequence_id')->nullable();
            if (!Schema::hasColumn($table, 'step_number'))  $t->integer('step_number')->default(1);
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->default('email');
            if (!Schema::hasColumn($table, 'delay_days'))   $t->integer('delay_days')->default(0);
            if (!Schema::hasColumn($table, 'content'))      $t->text('content')->nullable();
        });

        // ── hr_performance_reviews ────────────────────────────────────────
        $this->patch('hr_performance_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cycle_id'))       $t->unsignedBigInteger('cycle_id')->nullable();
            if (!Schema::hasColumn($table, 'employee_id'))    $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'reviewer_id'))    $t->unsignedBigInteger('reviewer_id')->nullable();
            if (!Schema::hasColumn($table, 'overall_score'))  $t->decimal('overall_score', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'comments'))       $t->text('comments')->nullable();
            if (!Schema::hasColumn($table, 'submitted_at'))   $t->timestamp('submitted_at')->nullable();
        });

        // ── hr_appraisal_cycles (more columns) ────────────────────────────
        $this->patch('hr_appraisal_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── hr_candidates ─────────────────────────────────────────────────
        $this->patch('hr_candidates', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'applied_at'))  $t->timestamp('applied_at')->nullable();
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'email'))       $t->string('email')->nullable();
            if (!Schema::hasColumn($table, 'job_id'))      $t->unsignedBigInteger('job_id')->nullable();
            if (!Schema::hasColumn($table, 'resume_url'))  $t->text('resume_url')->nullable();
        });

        // ── hr_critical_positions (more columns) ──────────────────────────
        $this->patch('hr_critical_positions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'current_holder_id'))  $t->unsignedBigInteger('current_holder_id')->nullable();
        });

        // ── hr_attendance (more columns) ──────────────────────────────────
        $this->patch('hr_attendance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'attendance_date'))  $t->date('attendance_date')->nullable();
        });

        // ── ecommerce_rmas (more columns) ─────────────────────────────────
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'requested_at'))  $t->timestamp('requested_at')->nullable();
        });

        // ── ecommerce_rfqs (more columns) ─────────────────────────────────
        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_id'))  $t->unsignedBigInteger('customer_id')->nullable();
        });

        // ── ecommerce_product_configurators (more columns) ────────────────
        $this->patch('ecommerce_product_configurators', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── ecommerce_subscription_plans (more columns) ───────────────────
        $this->patch('ecommerce_subscription_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── ecommerce_vendor_portal (more columns) ────────────────────────
        $this->patch('ecommerce_vendor_portal', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'logo_url'))  $t->text('logo_url')->nullable();
        });

        // ── ecommerce_vendors (more columns) ──────────────────────────────
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'verified_by'))  $t->unsignedBigInteger('verified_by')->nullable();
        });

        // ── ecom_shipments (more columns) ─────────────────────────────────
        $this->patch('ecom_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tracking_url'))  $t->text('tracking_url')->nullable();
        });

        // ── ecom_promotions (more columns) ────────────────────────────────
        $this->patch('ecom_promotions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'min_order_amount'))  $t->decimal('min_order_amount', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'max_uses'))          $t->integer('max_uses')->nullable();
            if (!Schema::hasColumn($table, 'used_count'))        $t->integer('used_count')->default(0);
            if (!Schema::hasColumn($table, 'starts_at'))         $t->timestamp('starts_at')->nullable();
            if (!Schema::hasColumn($table, 'ends_at'))           $t->timestamp('ends_at')->nullable();
        });

        // ── ecom_returns (more columns) ───────────────────────────────────
        $this->patch('ecom_returns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipment_id'))  $t->unsignedBigInteger('shipment_id')->nullable();
        });

        // ── ec_carts (more columns) ───────────────────────────────────────
        $this->patch('ec_carts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'discount_amount'))  $t->decimal('discount_amount', 15, 4)->default(0);
        });
    }

    public function down(): void {}
};
