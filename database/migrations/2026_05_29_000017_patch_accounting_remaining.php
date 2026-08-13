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
        // ── acc_consolidation_entities ────────────────────────────────────
        $this->patch('acc_consolidation_entities', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'consolidation_group_id'))  $t->unsignedBigInteger('consolidation_group_id')->nullable();
            if (!Schema::hasColumn($table, 'name'))                    $t->string('name')->nullable();
            if (!Schema::hasColumn($table, 'entity_code'))             $t->string('entity_code')->nullable();
            if (!Schema::hasColumn($table, 'ownership_pct'))           $t->decimal('ownership_pct', 5, 2)->default(100);
            if (!Schema::hasColumn($table, 'currency'))                $t->string('currency', 10)->default('XOF');
        });

        // ── acc_journal_entries (more columns) ────────────────────────────
        $this->patch('acc_journal_entries', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'fiscal_year_id'))  $t->unsignedBigInteger('fiscal_year_id')->nullable();
        });

        // ── acc_audit_logs (more columns) ─────────────────────────────────
        $this->patch('acc_audit_logs', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'reference_id'))  $t->unsignedBigInteger('reference_id')->nullable();
        });
    }

    public function down(): void {}
};
