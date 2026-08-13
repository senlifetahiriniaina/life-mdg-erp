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
        $this->patch('workflow_chain_definitions', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) $t->softDeletes();
            if (!Schema::hasColumn($table, 'trigger_key'))  $t->string('trigger_key', 100)->nullable();
            if (!Schema::hasColumn($table, 'is_active'))    $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void {}
};
