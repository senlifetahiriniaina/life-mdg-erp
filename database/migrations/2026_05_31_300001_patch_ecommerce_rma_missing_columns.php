<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing core columns to ecommerce_rmas that were not included in earlier patches.
 * The base stub table only had: id, tenant_id, status, data, timestamps, softDeletes.
 */
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
        $this->patch('ecommerce_rmas', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'customer_id'))  $t->unsignedBigInteger('customer_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'order_id'))     $t->unsignedBigInteger('order_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'reference'))    $t->string('reference')->nullable()->unique();
            if (!Schema::hasColumn($table, 'type'))         $t->string('type')->nullable();
            if (!Schema::hasColumn($table, 'reason'))       $t->string('reason')->nullable();
        });
    }

    public function down(): void {}
};
