<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function patch(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($table, $callback) {
            $callback($t, $table);
        });
    }

    public function up(): void
    {
        // ── pos_loyalty_transactions: add missing columns ─────────────────
        $this->patch('pos_loyalty_transactions', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'order_id'))
                $t->unsignedBigInteger('order_id')->nullable();
            if (! Schema::hasColumn($table, 'customer_id'))
                $t->unsignedBigInteger('customer_id')->nullable();
            if (! Schema::hasColumn($table, 'points'))
                $t->integer('points')->default(0);
            if (! Schema::hasColumn($table, 'type'))
                $t->string('type')->nullable();
            if (! Schema::hasColumn($table, 'balance_after'))
                $t->integer('balance_after')->default(0);
            if (! Schema::hasColumn($table, 'description'))
                $t->string('description')->nullable();
            if (! Schema::hasColumn($table, 'expires_at'))
                $t->timestamp('expires_at')->nullable();
        });

        // ── pos_returns: add missing columns ──────────────────────────────
        $this->patch('pos_returns', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'reference'))
                $t->string('reference')->nullable();
            if (! Schema::hasColumn($table, 'refund_amount'))
                $t->decimal('refund_amount', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'items'))
                $t->json('items')->nullable();
            if (! Schema::hasColumn($table, 'processed_at'))
                $t->timestamp('processed_at')->nullable();
            if (! Schema::hasColumn($table, 'processed_by'))
                $t->unsignedBigInteger('processed_by')->nullable();
        });

        // ── pos_return_lines: add missing columns ─────────────────────────
        $this->patch('pos_return_lines', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'order_line_id'))
                $t->unsignedBigInteger('order_line_id')->nullable();
            if (! Schema::hasColumn($table, 'quantity'))
                $t->integer('quantity')->default(0);
            if (! Schema::hasColumn($table, 'unit_price'))
                $t->decimal('unit_price', 12, 2)->default(0);
            if (! Schema::hasColumn($table, 'reason'))
                $t->string('reason')->nullable();
        });

        // ── pos_loyalty_programs: add missing columns ─────────────────────
        $this->patch('pos_loyalty_programs', function (Blueprint $t, $table) {
            if (! Schema::hasColumn($table, 'name'))
                $t->string('name')->nullable();
            if (! Schema::hasColumn($table, 'points_per_unit'))
                $t->decimal('points_per_unit', 8, 4)->default(1.0);
            if (! Schema::hasColumn($table, 'min_points_redeem'))
                $t->integer('min_points_redeem')->default(0);
            if (! Schema::hasColumn($table, 'points_value'))
                $t->decimal('points_value', 8, 4)->default(0.01);
            if (! Schema::hasColumn($table, 'tier_thresholds'))
                $t->json('tier_thresholds')->nullable();
            if (! Schema::hasColumn($table, 'is_active'))
                $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void {}
};
