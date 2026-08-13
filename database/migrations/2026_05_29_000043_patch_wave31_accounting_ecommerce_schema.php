<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (!Schema::hasTable($table)) return;
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // Accounting
        $this->patch('acc_consolidation_entities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'entity_type')) $t->string('entity_type')->nullable();
        });
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'currency')) $t->string('currency', 3)->default('XOF');
        });
        $this->patch('acc_reconciliations', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bank_statement_balance')) $t->decimal('bank_statement_balance', 15, 4)->default(0);
        });
        $this->patch('acc_tax_categories', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'name'))           $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'code'))           $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'description'))    $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'type'))           $t->string('type')->nullable();
            if (!Schema::hasColumn($table, 'jurisdiction'))   $t->string('jurisdiction', 10)->nullable();
            if (!Schema::hasColumn($table, 'effective_date')) $t->date('effective_date')->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))    $t->date('expiry_date')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))      $t->boolean('is_active')->default(true);
        });
        $this->patch('acc_tax_compliance', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'jurisdiction')) $t->string('jurisdiction', 10)->nullable();
        });

        // Ecommerce
        $this->patch('ec_cart_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sku')) $t->string('sku')->nullable();
        });
        $this->patch('ec_payments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'raw_response')) $t->text('raw_response')->nullable();
        });
        $this->patch('ec_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'user_id'))     $t->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn($table, 'approved_at')) $t->timestamp('approved_at')->nullable();
        });
        $this->patch('ecom_promotions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'expires_at')) $t->timestamp('expires_at')->nullable();
        });
        $this->patch('ecom_returns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes')) $t->text('notes')->nullable();
        });
        $this->patch('ecom_shipment_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id')) $t->unsignedBigInteger('product_id')->nullable();
        });
        $this->patch('ecom_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'created_by')) $t->unsignedBigInteger('created_by')->nullable();
            if (!Schema::hasColumn($table, 'weight_kg'))  $t->decimal('weight_kg', 10, 3)->nullable();
        });
        $this->patch('ecommerce_checkout_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cart_id')) $t->unsignedBigInteger('cart_id')->nullable();
        });
        $this->patch('ecommerce_product_configurators', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'min_custom_price')) $t->decimal('min_custom_price', 15, 4)->nullable();
        });
        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'expires_at')) $t->timestamp('expires_at')->nullable();
            if (!Schema::hasColumn($table, 'message'))    $t->text('message')->nullable();
        });
        $this->patch('ecommerce_rma_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_name')) $t->string('product_name')->nullable();
        });
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'inspection_completed_at')) $t->timestamp('inspection_completed_at')->nullable();
        });
        $this->patch('ecommerce_shipping_methods', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'per_kg_cost')) $t->decimal('per_kg_cost', 10, 4)->nullable();
        });
        $this->patch('ecommerce_subscription_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trial_days')) $t->integer('trial_days')->default(0);
        });
        $this->patch('ecommerce_themes', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_active')) $t->boolean('is_active')->default(true);
        });
        $this->patch('ecommerce_vendor_portal', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'bank_iban')) $t->string('bank_iban')->nullable();
        });
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_commissions')) $t->decimal('total_commissions', 15, 4)->default(0);
        });
    }

    public function down(): void {}
};
