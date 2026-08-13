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
        // ── crm_ai_agents (more) ──────────────────────────────────────────
        $this->patch('crm_ai_agents', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'trigger_config'))  $t->text('trigger_config')->nullable();
        });

        // ── crm_sequence_steps (more) ─────────────────────────────────────
        $this->patch('crm_sequence_steps', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'order'))    $t->integer('order')->default(1);
            if (!Schema::hasColumn($table, 'subject'))  $t->string('subject')->nullable();
        });

        // ── crm_opportunity_scores (more) ─────────────────────────────────
        $this->patch('crm_opportunity_scores', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'total_score'))  $t->decimal('total_score', 8, 4)->default(0);
        });

        // ── hd_kb_articles (more) ─────────────────────────────────────────
        $this->patch('hd_kb_articles', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'tags'))  $t->text('tags')->nullable();
        });

        // ── hd_kb_categories (more) ───────────────────────────────────────
        $this->patch('hd_kb_categories', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'article_count'))  $t->integer('article_count')->default(0);
        });

        // ── hd_sla_breaches (more) ────────────────────────────────────────
        $this->patch('hd_sla_breaches', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'breach_type'))  $t->string('breach_type', 20)->default('response');
        });

        // ── hd_chat_sessions (more) ───────────────────────────────────────
        $this->patch('hd_chat_sessions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))  $t->string('status', 20)->default('waiting');
        });

        // ── helpdesk_forum_posts (more) ───────────────────────────────────
        $this->patch('helpdesk_forum_posts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))  $t->string('status', 20)->default('open');
        });

        // ── ecommerce_vendors (more) ──────────────────────────────────────
        $this->patch('ecommerce_vendors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'balance'))  $t->decimal('balance', 15, 4)->default(0);
        });

        // ── ecommerce_rmas (more) ─────────────────────────────────────────
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'shipped_back_at'))  $t->timestamp('shipped_back_at')->nullable();
        });

        // ── ecommerce_rfqs (more) ─────────────────────────────────────────
        $this->patch('ecommerce_rfqs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_phone'))  $t->string('customer_phone')->nullable();
        });

        // ── ecommerce_product_configurators (more) ────────────────────────
        $this->patch('ecommerce_product_configurators', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'allow_custom_price'))  $t->boolean('allow_custom_price')->default(false);
        });

        // ── ecommerce_subscription_plans (more) ───────────────────────────
        $this->patch('ecommerce_subscription_plans', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'cycle_count'))  $t->integer('cycle_count')->nullable();
        });

        // ── ecommerce_vendor_portal (more) ────────────────────────────────
        $this->patch('ecommerce_vendor_portal', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'commission_pct'))  $t->decimal('commission_pct', 5, 2)->default(0);
        });

        // ── ecom_shipments (more) ─────────────────────────────────────────
        $this->patch('ecom_shipments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'estimated_delivery'))  $t->timestamp('estimated_delivery')->nullable();
        });

        // ── ecom_returns (more) ───────────────────────────────────────────
        $this->patch('ecom_returns', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'refund_amount'))  $t->decimal('refund_amount', 15, 4)->nullable();
        });

        // ── ecom_promotions (more) ────────────────────────────────────────
        $this->patch('ecom_promotions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'max_uses_per_customer'))  $t->integer('max_uses_per_customer')->nullable();
        });

        // ── ec_cart_items ─────────────────────────────────────────────────
        $this->patch('ec_cart_items', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_name'))  $t->string('product_name')->nullable();
            if (!Schema::hasColumn($table, 'cart_id'))       $t->unsignedBigInteger('cart_id')->nullable();
            if (!Schema::hasColumn($table, 'product_id'))    $t->unsignedBigInteger('product_id')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))      $t->integer('quantity')->default(1);
            if (!Schema::hasColumn($table, 'unit_price'))    $t->decimal('unit_price', 15, 4)->default(0);
        });

        // ── ec_reviews (more) ─────────────────────────────────────────────
        $this->patch('ec_reviews', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'status'))  $t->string('status', 20)->default('pending');
        });
    }

    public function down(): void {}
};
