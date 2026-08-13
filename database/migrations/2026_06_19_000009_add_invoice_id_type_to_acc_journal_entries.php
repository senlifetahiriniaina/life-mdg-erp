<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acc_journal_entries')) {
            return;
        }

        Schema::table('acc_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('acc_journal_entries', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
            }
            if (! Schema::hasColumn('acc_journal_entries', 'type')) {
                $table->string('type')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('acc_journal_entries')) {
            return;
        }

        Schema::table('acc_journal_entries', function (Blueprint $table) {
            foreach (['invoice_id', 'type'] as $col) {
                if (Schema::hasColumn('acc_journal_entries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
