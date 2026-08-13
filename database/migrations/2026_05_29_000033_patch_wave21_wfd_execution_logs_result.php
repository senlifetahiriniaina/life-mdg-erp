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
        $this->patch('wfd_execution_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'result'))             $t->text('result')->nullable();
        });
    }

    public function down(): void {}
};
