<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_invoice_lines')) {
            return;
        }

        Schema::table('acc_invoice_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('acc_invoice_lines', 'discount_percent')) {
                $table->decimal('discount_percent', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('acc_invoice_lines', 'tax_percent')) {
                $table->decimal('tax_percent', 8, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acc_invoice_lines')) {
            return;
        }

        Schema::table('acc_invoice_lines', function (Blueprint $table) {
            foreach (['discount_percent', 'tax_percent'] as $col) {
                if (Schema::hasColumn('acc_invoice_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
