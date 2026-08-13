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
        $this->patch('ec_cart_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'attributes')) $t->json('attributes')->nullable();
        });

        $this->patch('ecom_promotion_uses', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'promotion_id'))    $t->unsignedBigInteger('promotion_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_id'))        $t->unsignedBigInteger('order_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'customer_id'))     $t->unsignedBigInteger('customer_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'discount_amount')) $t->decimal('discount_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'used_at'))         $t->timestamp('used_at')->nullable();
        });

        $this->patch('ecom_returns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approved_at'))  $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'received_at'))  $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'requested_at')) $t->timestamp('requested_at')->nullable();
        });

        $this->patch('ecom_shipment_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipment_id'))  $t->unsignedBigInteger('shipment_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'product_name')) $t->string('product_name')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))     $t->decimal('quantity', 15, 2)->default(1);
            if (!Schema::hasColumn($table, 'sku'))          $t->string('sku')->nullable();
            if (!Schema::hasColumn($table, 'unit_price'))   $t->decimal('unit_price', 15, 2)->default(0);
        });

        $this->patch('ecom_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes')) $t->text('notes')->nullable();
        });

        $this->patch('ecommerce_checkout_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'payment_method'))   $t->string('payment_method')->nullable();
            if (!Schema::hasColumn($table, 'shipping_address')) $t->json('shipping_address')->nullable();
            if (!Schema::hasColumn($table, 'billing_address'))  $t->json('billing_address')->nullable();
            if (!Schema::hasColumn($table, 'subtotal'))         $t->decimal('subtotal', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_amount'))       $t->decimal('tax_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'discount_amount'))  $t->decimal('discount_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'shipping_cost'))    $t->decimal('shipping_cost', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'total'))            $t->decimal('total', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'payment_intent_id'))$t->string('payment_intent_id')->nullable();
        });

        $this->patch('ecommerce_commissions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id'))          $t->unsignedBigInteger('vendor_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_id'))           $t->unsignedBigInteger('order_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_amount'))       $t->decimal('order_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'commission_pct'))     $t->decimal('commission_pct', 5, 2)->default(0);
            if (!Schema::hasColumn($table, 'commission_amount'))  $t->decimal('commission_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'status'))             $t->string('status')->default('pending');
            if (!Schema::hasColumn($table, 'period'))             $t->string('period')->nullable();
            if (!Schema::hasColumn($table, 'paid_at'))            $t->timestamp('paid_at')->nullable();
        });

        $this->patch('ecommerce_product_configurators', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'max_custom_price'))      $t->decimal('max_custom_price', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'base_price_adjustment'))  $t->decimal('base_price_adjustment', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'summary_format'))         $t->string('summary_format')->nullable();
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_name')) $t->string('product_name')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))     $t->decimal('quantity', 15, 2)->default(1);
        });

        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'internal_notes')) $t->text('internal_notes')->nullable();
            if (!Schema::hasColumn($table, 'quoted_at'))      $t->timestamp('quoted_at')->nullable();
        });

        $this->patch('ecommerce_rma_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity'))   $t->decimal('quantity', 15, 2)->default(1);
            if (!Schema::hasColumn($table, 'unit_price')) $t->decimal('unit_price', 15, 2)->default(0);
        });

        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'completed_at'))           $t->timestamp('completed_at')->nullable();
            if (!Schema::hasColumn($table, 'authorized_by'))          $t->unsignedBigInteger('authorized_by')->nullable();
            if (!Schema::hasColumn($table, 'reason_details'))         $t->text('reason_details')->nullable();
            if (!Schema::hasColumn($table, 'shipped_back_at'))        $t->timestamp('shipped_back_at')->nullable();
            if (!Schema::hasColumn($table, 'inspection_notes'))       $t->text('inspection_notes')->nullable();
            if (!Schema::hasColumn($table, 'refund_method'))          $t->string('refund_method')->nullable();
            if (!Schema::hasColumn($table, 'refund_amount'))          $t->decimal('refund_amount', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'requested_at'))           $t->timestamp('requested_at')->nullable();
            if (!Schema::hasColumn($table, 'received_at'))            $t->timestamp('received_at')->nullable();
            if (!Schema::hasColumn($table, 'inspection_completed_at'))$t->timestamp('inspection_completed_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_at'))            $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'refund_processed_at'))    $t->timestamp('refund_processed_at')->nullable();
            if (!Schema::hasColumn($table, 'internal_notes'))         $t->text('internal_notes')->nullable();
        });

        $this->patch('ecommerce_shipping_methods', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'free_over'))     $t->decimal('free_over', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'max_weight_kg')) $t->decimal('max_weight_kg', 10, 2)->nullable();
        });

        $this->patch('ecommerce_subscription_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'max_renewals')) $t->integer('max_renewals')->nullable();
        });

        $this->patch('ecommerce_themes', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'preview_url')) $t->string('preview_url')->nullable();
        });

        $this->patch('ecommerce_vendor_portal', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approved_at')) $t->timestamp('approved_at')->nullable();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subscription_number')) $t->string('subscription_number')->nullable();
        });

        $this->patch('ecommerce_vendor_portal_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id')) $t->unsignedBigInteger('vendor_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'return_rate'))          $t->decimal('return_rate', 5, 2)->nullable();
            if (!Schema::hasColumn($table, 'average_rating'))       $t->decimal('average_rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'review_count'))         $t->unsignedInteger('review_count')->default(0);
            if (!Schema::hasColumn($table, 'response_time_hours'))  $t->decimal('response_time_hours', 10, 2)->nullable();
            if (!Schema::hasColumn($table, 'internal_notes'))       $t->text('internal_notes')->nullable();
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id')) $t->unsignedBigInteger('vendor_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendor_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id')) $t->unsignedBigInteger('vendor_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id')) $t->unsignedBigInteger('vendor_id')->nullable()->index();
        });

        $this->patch('ecommerce_configurator_option_values', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'price_adjustment'))  $t->decimal('price_adjustment', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'weight_adjustment')) $t->decimal('weight_adjustment', 10, 3)->default(0);
        });

        $this->patch('ecommerce_configurator_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'action_type'))     $t->string('action_type')->nullable();
            if (!Schema::hasColumn($table, 'condition_logic')) $t->json('condition_logic')->nullable();
            if (!Schema::hasColumn($table, 'condition_data'))  $t->json('condition_data')->nullable();
            if (!Schema::hasColumn($table, 'is_active'))       $t->boolean('is_active')->default(true);
        });

        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'converted_order_id')) $t->unsignedBigInteger('converted_order_id')->nullable();
        });

        $this->patch('ecommerce_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipping_method_id')) $t->unsignedBigInteger('shipping_method_id')->nullable()->index();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'discount_amount')) $t->decimal('discount_amount', 15, 2)->default(0);
        });

        $this->patch('ecommerce_configurator_options', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'configurator_id')) $t->unsignedBigInteger('configurator_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'name'))            $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'label'))           $t->string('label')->nullable();
            if (!Schema::hasColumn($table, 'option_type'))     $t->string('option_type')->nullable();
            if (!Schema::hasColumn($table, 'description'))     $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'is_required'))     $t->boolean('is_required')->default(false);
            if (!Schema::hasColumn($table, 'sort_order'))      $t->integer('sort_order')->default(0);
        });

        $this->patch('ecommerce_configurator_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'configurator_id')) $t->unsignedBigInteger('configurator_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'name'))            $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'description'))     $t->text('description')->nullable();
            if (!Schema::hasColumn($table, 'rule_type'))       $t->string('rule_type')->nullable();
        });

        $this->patch('ecommerce_configurator_option_values', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'option_id'))      $t->unsignedBigInteger('option_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'value'))          $t->string('value')->nullable();
            if (!Schema::hasColumn($table, 'label'))          $t->string('label')->nullable();
            if (!Schema::hasColumn($table, 'price_modifier')) $t->decimal('price_modifier', 15, 2)->default(0);
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'target_price')) $t->decimal('target_price', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'unit'))         $t->string('unit')->nullable();
            if (!Schema::hasColumn($table, 'rfq_id'))       $t->unsignedBigInteger('rfq_id')->nullable()->index();
        });

        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'accepted_at')) $t->timestamp('accepted_at')->nullable();
            if (!Schema::hasColumn($table, 'rejected_at')) $t->timestamp('rejected_at')->nullable();
        });

        $this->patch('ecommerce_rma_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'condition')) $t->string('condition')->nullable();
            if (!Schema::hasColumn($table, 'rma_id'))    $t->unsignedBigInteger('rma_id')->nullable()->index();
        });

        $this->patch('ecommerce_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'order_id')) $t->unsignedBigInteger('order_id')->nullable()->index();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'next_billing_at')) $t->timestamp('next_billing_at')->nullable();
        });

        $this->patch('ecommerce_shipping_methods', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'countries')) $t->json('countries')->nullable();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'started_at'))      $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'discount_amount')) $t->decimal('discount_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'discount_code'))   $t->string('discount_code')->nullable();
        });

        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'converted_order_id')) $t->unsignedBigInteger('converted_order_id')->nullable();
            if (!Schema::hasColumn($table, 'assigned_to'))        $t->unsignedBigInteger('assigned_to')->nullable();
        });

        $this->patch('ecommerce_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipping_method_id')) $t->unsignedBigInteger('shipping_method_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'carrier'))            $t->string('carrier')->nullable();
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id'))  $t->unsignedBigInteger('vendor_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'product_id')) $t->unsignedBigInteger('product_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendor_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id')) $t->unsignedBigInteger('vendor_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_id'))  $t->unsignedBigInteger('order_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'vendor_id'))    $t->unsignedBigInteger('vendor_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'reference'))    $t->string('reference')->nullable();
            if (!Schema::hasColumn($table, 'period_start')) $t->date('period_start')->nullable();
            if (!Schema::hasColumn($table, 'period_end'))   $t->date('period_end')->nullable();
        });

        $this->patch('ecommerce_billing_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'subscription_id')) $t->unsignedBigInteger('subscription_id')->nullable()->index();
        });

        $this->patch('ecommerce_configurator_option_values', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_available')) $t->boolean('is_available')->default(true);
        });

        $this->patch('ecommerce_configurator_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'action_data')) $t->json('action_data')->nullable();
        });

        $this->patch('ecommerce_rfq_communications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'rfq_id'))   $t->unsignedBigInteger('rfq_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'message'))  $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'sender_id'))$t->unsignedBigInteger('sender_id')->nullable();
            if (!Schema::hasColumn($table, 'type'))     $t->string('type')->nullable();
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id')) $t->unsignedBigInteger('product_id')->nullable()->index();
        });

        $this->patch('ecommerce_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tracking_number')) $t->string('tracking_number')->nullable();
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sku')) $t->string('sku')->nullable();
        });

        $this->patch('ecommerce_vendor_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_id')) $t->unsignedBigInteger('customer_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'rating'))      $t->decimal('rating', 4, 2)->nullable();
            if (!Schema::hasColumn($table, 'title'))       $t->string('title')->nullable();
            if (!Schema::hasColumn($table, 'comment'))     $t->text('comment')->nullable();
        });

        $this->patch('ecommerce_billing_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cycle_number')) $t->unsignedInteger('cycle_number')->default(1);
        });

        $this->patch('ecommerce_configurator_option_values', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sort_order')) $t->integer('sort_order')->default(0);
        });

        $this->patch('ecommerce_configurator_rules', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sort_order')) $t->integer('sort_order')->default(0);
        });

        $this->patch('ecommerce_rfq_communications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sender_type')) $t->string('sender_type')->nullable();
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sku')) $t->string('sku')->nullable();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'gross_sales')) $t->decimal('gross_sales', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'net_sales'))   $t->decimal('net_sales', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'fees'))        $t->decimal('fees', 15, 2)->default(0);
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cost_price'))  $t->decimal('cost_price', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'sale_price'))  $t->decimal('sale_price', 15, 2)->nullable();
        });

        $this->patch('ecommerce_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipped_at')) $t->timestamp('shipped_at')->nullable();
        });

        $this->patch('ecommerce_billing_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'started_at')) $t->timestamp('started_at')->nullable();
            if (!Schema::hasColumn($table, 'ended_at'))   $t->timestamp('ended_at')->nullable();
            if (!Schema::hasColumn($table, 'amount'))     $t->decimal('amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'status'))     $t->string('status')->default('pending');
        });

        $this->patch('ecommerce_configurator_option_values', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'sku_suffix')) $t->string('sku_suffix')->nullable();
        });

        $this->patch('ecommerce_rfq_communications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'attachments')) $t->json('attachments')->nullable();
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quoted_price')) $t->decimal('quoted_price', 15, 2)->nullable();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'commissions'))  $t->decimal('commissions', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'returns'))      $t->decimal('returns', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'net_payout'))   $t->decimal('net_payout', 15, 2)->default(0);
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'quantity_available')) $t->integer('quantity_available')->default(0);
        });

        $this->patch('ecommerce_vendor_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_verified_purchase')) $t->boolean('is_verified_purchase')->default(false);
            if (!Schema::hasColumn($table, 'helpful_count'))        $t->unsignedInteger('helpful_count')->default(0);
        });

        $this->patch('ecommerce_billing_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'ends_at')) $t->timestamp('ends_at')->nullable();
        });

        $this->patch('ecommerce_rfq_communications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'is_internal')) $t->boolean('is_internal')->default(false);
        });

        $this->patch('ecommerce_rfq_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'notes')) $t->text('notes')->nullable();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'refunds')) $t->decimal('refunds', 15, 2)->default(0);
        });

        $this->patch('ecommerce_vendor_products', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'visibility')) $t->string('visibility')->default('public');
            if (!Schema::hasColumn($table, 'listed_at'))  $t->timestamp('listed_at')->nullable();
        });

        $this->patch('ecommerce_billing_cycles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'discount_amount')) $t->decimal('discount_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'tax_amount'))      $t->decimal('tax_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'charged_at'))      $t->timestamp('charged_at')->nullable();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'payout_amount')) $t->decimal('payout_amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'paid_at'))       $t->timestamp('paid_at')->nullable();
        });

        $this->patch('ecommerce_vendor_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'approved_at')) $t->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn($table, 'approved_by')) $t->unsignedBigInteger('approved_by')->nullable();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'ends_at'))       $t->timestamp('ends_at')->nullable();
            if (!Schema::hasColumn($table, 'renewal_count')) $t->unsignedInteger('renewal_count')->default(0);
        });

        $this->patch('ecommerce_recurring_charges', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'billing_cycle_id')) $t->unsignedBigInteger('billing_cycle_id')->nullable()->index();
        });

        $this->patch('ecommerce_vendor_payouts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'method')) $t->string('method')->nullable();
        });

        $this->patch('ecommerce_rma_communications', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'rma_id'))      $t->unsignedBigInteger('rma_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'sender_id'))   $t->unsignedBigInteger('sender_id')->nullable();
            if (!Schema::hasColumn($table, 'sender_type')) $t->string('sender_type')->nullable();
            if (!Schema::hasColumn($table, 'message'))     $t->text('message')->nullable();
            if (!Schema::hasColumn($table, 'attachments')) $t->json('attachments')->nullable();
            if (!Schema::hasColumn($table, 'is_internal')) $t->boolean('is_internal')->default(false);
        });

        $this->patch('ecommerce_rma_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'rma_id'))           $t->unsignedBigInteger('rma_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_item_id'))    $t->unsignedBigInteger('order_item_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))       $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'product_name'))     $t->string('product_name')->nullable();
            if (!Schema::hasColumn($table, 'sku'))              $t->string('sku')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))         $t->decimal('quantity', 15, 2)->default(1);
            if (!Schema::hasColumn($table, 'unit_price'))       $t->decimal('unit_price', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'condition'))        $t->string('condition')->nullable();
            if (!Schema::hasColumn($table, 'inspection_result'))$t->string('inspection_result')->nullable();
            if (!Schema::hasColumn($table, 'notes'))            $t->text('notes')->nullable();
        });

        $this->patch('ecommerce_recurring_charges', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'charge_id'))      $t->string('charge_id')->nullable();
            if (!Schema::hasColumn($table, 'amount'))         $t->decimal('amount', 15, 2)->default(0);
            if (!Schema::hasColumn($table, 'status'))         $t->string('status')->default('pending');
            if (!Schema::hasColumn($table, 'payment_method')) $t->string('payment_method')->nullable();
            if (!Schema::hasColumn($table, 'attempt_count'))  $t->unsignedInteger('attempt_count')->default(0);
            if (!Schema::hasColumn($table, 'processed_at'))   $t->timestamp('processed_at')->nullable();
        });

        $this->patch('ecommerce_subscriptions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cancellation_reason')) $t->string('cancellation_reason')->nullable();
            if (!Schema::hasColumn($table, 'cancelled_at'))        $t->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void {}
};
