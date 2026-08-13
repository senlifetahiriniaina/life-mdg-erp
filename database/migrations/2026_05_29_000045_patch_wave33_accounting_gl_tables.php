<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('acc_gl_journals')) {
            Schema::create('acc_gl_journals', function (Blueprint $t) {
                $t->id();
                $t->string('code')->nullable();
                $t->string('name')->nullable();
                $t->string('type')->default('general');
                $t->text('description')->nullable();
                $t->unsignedBigInteger('company_id')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('acc_gl_entries')) {
            Schema::create('acc_gl_entries', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('gl_account_id')->nullable();
                $t->unsignedBigInteger('gl_journal_id')->nullable();
                $t->unsignedBigInteger('journal_entry_id')->nullable();
                $t->date('date')->nullable();
                $t->text('description')->nullable();
                $t->decimal('debit_amount', 15, 4)->default(0);
                $t->decimal('credit_amount', 15, 4)->default(0);
                $t->string('currency', 3)->default('USD');
                $t->decimal('exchange_rate', 15, 6)->default(1);
                $t->unsignedBigInteger('company_id')->nullable();
                $t->string('reference')->nullable();
                $t->string('source_type')->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->string('period')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void {}
};
