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
        // ── logistics_carriers (more columns) ──────────────────────────────
        $this->patch('logistics_carriers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))            $t->string('code', 20)->nullable();
            if (!Schema::hasColumn($table, 'type'))            $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'contact_email'))   $t->string('contact_email')->nullable();
            if (!Schema::hasColumn($table, 'contact_phone'))   $t->string('contact_phone')->nullable();
            if (!Schema::hasColumn($table, 'country'))         $t->string('country', 10)->nullable();
            if (!Schema::hasColumn($table, 'rating'))          $t->decimal('rating', 3, 2)->nullable();
        });

        // ── logistics_shipments ────────────────────────────────────────────
        $this->patch('logistics_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))                $t->string('type', 20)->default('outbound');
            if (!Schema::hasColumn($table, 'reference'))           $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'shipper_name'))        $t->string('shipper_name')->nullable();
            if (!Schema::hasColumn($table, 'shipper_address'))     $t->text('shipper_address')->nullable();
            if (!Schema::hasColumn($table, 'shipper_city'))        $t->string('shipper_city')->nullable();
            if (!Schema::hasColumn($table, 'shipper_country'))     $t->string('shipper_country', 10)->nullable();
            if (!Schema::hasColumn($table, 'consignee_name'))      $t->string('consignee_name')->nullable();
            if (!Schema::hasColumn($table, 'consignee_address'))   $t->text('consignee_address')->nullable();
            if (!Schema::hasColumn($table, 'consignee_city'))      $t->string('consignee_city')->nullable();
            if (!Schema::hasColumn($table, 'consignee_country'))   $t->string('consignee_country', 10)->nullable();
            if (!Schema::hasColumn($table, 'transport_mode'))      $t->string('transport_mode', 20)->default('road');
            if (!Schema::hasColumn($table, 'weight_kg'))           $t->decimal('weight_kg', 10, 3)->nullable();
            if (!Schema::hasColumn($table, 'incoterm'))            $t->string('incoterm', 10)->nullable();
            if (!Schema::hasColumn($table, 'requires_cold_chain')) $t->boolean('requires_cold_chain')->default(false);
            if (!Schema::hasColumn($table, 'has_hazmat'))          $t->boolean('has_hazmat')->default(false);
            if (!Schema::hasColumn($table, 'carrier_id'))          $t->unsignedBigInteger('carrier_id')->nullable();
            if (!Schema::hasColumn($table, 'tracking_number'))     $t->string('tracking_number')->nullable();
            if (!Schema::hasColumn($table, 'shipped_at'))          $t->timestamp('shipped_at')->nullable();
            if (!Schema::hasColumn($table, 'estimated_delivery'))  $t->timestamp('estimated_delivery')->nullable();
            if (!Schema::hasColumn($table, 'delivered_at'))        $t->timestamp('delivered_at')->nullable();
        });

        // ── ec_carts ─────────────────────────────────────────────────────
        $this->patch('ec_carts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'coupon_code')) $t->string('coupon_code')->nullable();
            if (!Schema::hasColumn($table, 'subtotal'))    $t->decimal('subtotal', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'discount'))    $t->decimal('discount', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'items'))       $t->text('items')->nullable();
        });

        // ── ec_reviews ────────────────────────────────────────────────────
        $this->patch('ec_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reviewer_name'))  $t->string('reviewer_name')->nullable();
            if (!Schema::hasColumn($table, 'reviewer_email')) $t->string('reviewer_email')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))     $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'rating'))         $t->tinyInteger('rating')->default(5);
            if (!Schema::hasColumn($table, 'title'))          $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'body'))           $t->text('body')->nullable();
            if (!Schema::hasColumn($table, 'is_verified'))    $t->boolean('is_verified')->default(false);
        });

        // ── ecom_promotions (more columns) ────────────────────────────────
        $this->patch('ecom_promotions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'code'))             $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'conditions'))       $t->text('conditions')->nullable();
            if (!Schema::hasColumn($table, 'type'))             $t->string('type', 20)->default('percentage');
            if (!Schema::hasColumn($table, 'value'))            $t->decimal('value', 15, 4)->default(0);
        });

        // ── ecom_shipments (more columns) ─────────────────────────────────
        $this->patch('ecom_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipping_cost'))       $t->decimal('shipping_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'actual_delivery'))     $t->timestamp('actual_delivery')->nullable();
        });

        // ── ecom_returns (more columns) ───────────────────────────────────
        $this->patch('ecom_returns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'requested_at'))  $t->timestamp('requested_at')->nullable();
            if (!Schema::hasColumn($table, 'resolved_at'))   $t->timestamp('resolved_at')->nullable();
        });

        // ── ecommerce_vendors (more columns) ──────────────────────────────
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'store_name'))      $t->string('store_name')->nullable();
            if (!Schema::hasColumn($table, 'store_slug'))      $t->string('store_slug')->nullable();
            if (!Schema::hasColumn($table, 'description'))     $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'logo_url'))        $t->text('logo_url')->nullable();
            if (!Schema::hasColumn($table, 'banner_url'))      $t->text('banner_url')->nullable();
            if (!Schema::hasColumn($table, 'contact_email'))   $t->string('contact_email')->nullable();
            if (!Schema::hasColumn($table, 'contact_phone'))   $t->string('contact_phone')->nullable();
        });

        // ── ecommerce_vendor_portal ───────────────────────────────────────
        $this->patch('ecommerce_vendor_portal', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))        $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description')) $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'slug'))        $t->string('slug')->nullable();
        });

        // ── ecommerce_themes ──────────────────────────────────────────────
        $this->patch('ecommerce_themes', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))     $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'slug'))     $t->string('slug')->nullable();
            if (!Schema::hasColumn($table, 'store_id')) $t->unsignedBigInteger('store_id')->nullable();
            if (!Schema::hasColumn($table, 'config'))   $t->text('config')->nullable();
        });

        // ── ecommerce_shipping_methods ────────────────────────────────────
        $this->patch('ecommerce_shipping_methods', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))               $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'carrier'))            $t->string('carrier')->nullable();
            if (!Schema::hasColumn($table, 'type'))               $t->string('type', 20)->nullable();
            if (!Schema::hasColumn($table, 'base_cost'))          $t->decimal('base_cost', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'estimated_days_min')) $t->integer('estimated_days_min')->nullable();
            if (!Schema::hasColumn($table, 'estimated_days_max')) $t->integer('estimated_days_max')->nullable();
            if (!Schema::hasColumn($table, 'active'))             $t->boolean('active')->default(true);
            if (!Schema::hasColumn($table, 'store_id'))           $t->unsignedBigInteger('store_id')->nullable();
        });

        // ── ecommerce_rmas (more columns) ─────────────────────────────────
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))            $t->string('type', 20)->default('return');
            if (!Schema::hasColumn($table, 'customer_email'))  $t->string('customer_email')->nullable();
        });

        // ── ecommerce_rfqs (more columns) ─────────────────────────────────
        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_email')) $t->string('customer_email')->nullable();
            if (!Schema::hasColumn($table, 'customer_name'))  $t->string('customer_name')->nullable();
        });

        // ── hr_review_cycles (more columns) ───────────────────────────────
        $this->patch('hr_review_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'period_start'))  $t->date('period_start')->nullable();
            if (!Schema::hasColumn($table, 'period_end'))    $t->date('period_end')->nullable();
            if (!Schema::hasColumn($table, 'type'))          $t->string('type', 20)->default('annual');
        });

        // ── hr_appraisal_cycles (more columns) ────────────────────────────
        $this->patch('hr_appraisal_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cycle_type')) $t->string('cycle_type', 20)->default('annual');
            if (!Schema::hasColumn($table, 'year'))       $t->integer('year')->nullable();
        });

        // ── hr_performance_cycles ─────────────────────────────────────────
        $this->patch('hr_performance_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'start_date'))   $t->date('start_date')->nullable();
            if (!Schema::hasColumn($table, 'end_date'))     $t->date('end_date')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── hr_attendance_records ─────────────────────────────────────────
        $this->patch('hr_attendance_records', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'employee_id'))  $t->unsignedBigInteger('employee_id')->nullable();
            if (!Schema::hasColumn($table, 'date'))         $t->date('date')->nullable();
            if (!Schema::hasColumn($table, 'check_in'))     $t->timestamp('check_in')->nullable();
            if (!Schema::hasColumn($table, 'check_out'))    $t->timestamp('check_out')->nullable();
            if (!Schema::hasColumn($table, 'hours_worked')) $t->decimal('hours_worked', 6, 2)->nullable();
            if (!Schema::hasColumn($table, 'source'))       $t->string('source', 20)->default('manual');
        });

        // ── hr_succession_plans (more columns) ────────────────────────────
        $this->patch('hr_succession_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'fiscal_year'))  $t->integer('fiscal_year')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── hr_skills ─────────────────────────────────────────────────────
        $this->patch('hr_skills', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))         $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'category'))     $t->string('category')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
        });

        // ── hr_salary_bands ───────────────────────────────────────────────
        $this->patch('hr_salary_bands', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))      $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'level'))      $t->string('level')->nullable();
            if (!Schema::hasColumn($table, 'min_salary')) $t->decimal('min_salary', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'max_salary')) $t->decimal('max_salary', 15, 4)->nullable();
            if (!Schema::hasColumn($table, 'currency'))   $t->string('currency', 10)->default('XOF');
        });

        // ── hr_recruitment_jobs ───────────────────────────────────────────
        $this->patch('hr_recruitment_jobs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))        $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'department_id')) $t->unsignedBigInteger('department_id')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });

        // ── hr_job_postings (more columns) ────────────────────────────────
        $this->patch('hr_job_postings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'work_mode'))    $t->string('work_mode', 20)->default('onsite');
        });

        // ── hr_training_courses ───────────────────────────────────────────
        $this->patch('hr_training_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'type'))         $t->string('type', 20)->default('internal');
            if (!Schema::hasColumn($table, 'duration_hours')) $t->integer('duration_hours')->nullable();
            if (!Schema::hasColumn($table, 'provider'))     $t->string('provider')->nullable();
        });

        // ── hr_courses ────────────────────────────────────────────────────
        $this->patch('hr_courses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'title'))        $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'description'))  $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'duration'))     $t->integer('duration')->nullable();
            if (!Schema::hasColumn($table, 'category'))     $t->string('category')->nullable();
        });
    }

    public function down(): void {}
};
