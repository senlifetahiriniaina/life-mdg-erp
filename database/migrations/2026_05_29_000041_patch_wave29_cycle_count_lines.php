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
        $this->patch('inventory_cycle_count_lines', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'variance_value'))     $t->decimal('variance_value', 15, 2)->nullable();
            if (!Schema::hasColumn($table, 'system_qty'))         $t->decimal('system_qty', 15, 4)->default(0);
            if (!Schema::hasColumn($table, 'status'))             $t->string('status', 20)->default('pending');
        });
    }

    public function down(): void {}
};
