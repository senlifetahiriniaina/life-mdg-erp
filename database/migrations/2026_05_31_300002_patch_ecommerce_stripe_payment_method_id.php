<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ec_payments')) {
            Schema::table('ec_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('ec_payments', 'stripe_payment_method_id')) {
                    $table->string('stripe_payment_method_id')->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'gateway_payment_id')) {
                    $table->string('gateway_payment_id')->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'gateway_order_id')) {
                    $table->string('gateway_order_id')->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'payment_method_type')) {
                    $table->string('payment_method_type')->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'card_brand')) {
                    $table->string('card_brand')->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'card_last4')) {
                    $table->string('card_last4', 4)->nullable();
                }
                if (! Schema::hasColumn('ec_payments', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable();
                }
                // Make payment_method nullable (tests may not provide it)
                // SQLite does not support ALTER COLUMN, so we skip this on SQLite
                // The NOT NULL is enforced at the original table creation; we handle it in fillable defaults
            });
        }

        if (Schema::hasTable('ec_orders') && ! Schema::hasColumn('ec_orders', 'stripe_payment_method_id')) {
            Schema::table('ec_orders', function (Blueprint $table) {
                $table->string('stripe_payment_method_id')->nullable();
            });
        }

        if (Schema::hasTable('ecommerce_checkout_sessions') && ! Schema::hasColumn('ecommerce_checkout_sessions', 'shipping_method_id')) {
            Schema::table('ecommerce_checkout_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('shipping_method_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ec_payments') && Schema::hasColumn('ec_payments', 'stripe_payment_method_id')) {
            Schema::table('ec_payments', function (Blueprint $table) {
                $table->dropColumn('stripe_payment_method_id');
            });
        }

        if (Schema::hasTable('ec_orders') && Schema::hasColumn('ec_orders', 'stripe_payment_method_id')) {
            Schema::table('ec_orders', function (Blueprint $table) {
                $table->dropColumn('stripe_payment_method_id');
            });
        }
    }
};
