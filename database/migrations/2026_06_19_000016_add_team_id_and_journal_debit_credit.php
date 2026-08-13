<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hd_tickets') && ! Schema::hasColumn('hd_tickets', 'team_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('acc_journal_entries')) {
            Schema::table('acc_journal_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('acc_journal_entries', 'debit')) {
                    $table->decimal('debit', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('acc_journal_entries', 'credit')) {
                    $table->decimal('credit', 15, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_tickets') && Schema::hasColumn('hd_tickets', 'team_id')) {
            Schema::table('hd_tickets', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }
        if (Schema::hasTable('acc_journal_entries')) {
            Schema::table('acc_journal_entries', function (Blueprint $table) {
                foreach (['debit', 'credit'] as $col) {
                    if (Schema::hasColumn('acc_journal_entries', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
