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
        $this->patch('mfg_iot_devices', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'last_seen_at')) $t->timestamp('last_seen_at')->nullable();
            if (!Schema::hasColumn($table, 'metadata'))     $t->text('metadata')->nullable();
            if (!Schema::hasColumn($table, 'api_key'))      $t->string('api_key')->nullable();
        });

        $this->patch('mfg_lot_numbers', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'product_id'))          $t->unsignedBigInteger('product_id')->nullable()->index();
            if (!Schema::hasColumn($table, 'production_order_id')) $t->unsignedBigInteger('production_order_id')->nullable();
            if (!Schema::hasColumn($table, 'expiry_date'))         $t->date('expiry_date')->nullable();
            if (!Schema::hasColumn($table, 'quantity'))            $t->decimal('quantity', 15, 4)->default(0);
        });

        $this->patch('mfg_routings', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'operation'))       $t->string('operation')->nullable();
            if (!Schema::hasColumn($table, 'code'))            $t->string('code')->nullable();
            if (!Schema::hasColumn($table, 'status'))          $t->string('status')->default('active');
        });

        $this->patch('mfg_subcontractors', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'contact_phone')) $t->string('contact_phone')->nullable();
            if (!Schema::hasColumn($table, 'contact_email')) $t->string('contact_email')->nullable();
            if (!Schema::hasColumn($table, 'address'))       $t->text('address')->nullable();
        });

        $this->patch('mfg_subcontracts', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'supplier_name'))  $t->string('supplier_name')->nullable();
            if (!Schema::hasColumn($table, 'work_order_id'))  $t->unsignedBigInteger('work_order_id')->nullable();
            if (!Schema::hasColumn($table, 'contract_value')) $t->decimal('contract_value', 15, 4)->nullable();
        });

        $this->patch('mfg_work_orders', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'priority')) $t->string('priority')->default('normal');
        });
    }

    public function down(): void {}
};
