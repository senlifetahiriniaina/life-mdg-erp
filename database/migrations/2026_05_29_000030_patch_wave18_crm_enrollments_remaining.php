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
        // ── CRM: crm_sequence_enrollments (next_send_at) ──────────────────
        $this->patch('crm_sequence_enrollments', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'next_send_at'))       $t->timestamp('next_send_at')->nullable();
        });

        // ── CRM: crm_leads (description) ──────────────────────────────────
        $this->patch('crm_leads', function (Blueprint $t, $table) {
            if (!Schema::hasColumn($table, 'description'))        $t->text('description')->nullable();
        });
    }

    public function down(): void {}
};
