<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes the missing onDelete clause on acc_journal_entry_lines.account_id.
 *
 * The original migration (2024_01_05_000001_create_accounting_tables) defined:
 *   $table->foreignId('account_id')->constrained('acc_chart_of_accounts');
 *
 * Without an explicit onDelete clause the behaviour is RESTRICT in MySQL, but
 * this is not self-documenting and can silently differ across database engines.
 * This migration drops the implicit FK and re-creates it with an explicit
 * onDelete('restrict') clause so the intent is clear and portable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return;
        }

        Schema::table('acc_journal_entry_lines', function (Blueprint $table): void {
            // Drop the existing implicit FK created by constrained('acc_chart_of_accounts').
            // Laravel names it {table}_{column}_foreign by convention.
            $table->dropForeign(['account_id']);

            // Re-add with explicit onDelete('restrict').
            $table->foreign('account_id')
                ->references('id')
                ->on('acc_chart_of_accounts')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acc_journal_entry_lines')) {
            return;
        }

        Schema::table('acc_journal_entry_lines', function (Blueprint $table): void {
            // Revert: drop the explicit FK and re-add without onDelete clause.
            $table->dropForeign(['account_id']);

            $table->foreign('account_id')
                ->references('id')
                ->on('acc_chart_of_accounts');
        });
    }
};
