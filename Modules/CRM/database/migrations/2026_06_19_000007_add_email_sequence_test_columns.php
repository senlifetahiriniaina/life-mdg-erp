<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_email_sequences') && ! Schema::hasColumn('crm_email_sequences', 'enabled')) {
            Schema::table('crm_email_sequences', function (Blueprint $table) {
                $table->boolean('enabled')->default(true);
            });
        }

        if (Schema::hasTable('crm_sequence_steps')) {
            Schema::table('crm_sequence_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_sequence_steps', 'step_order')) {
                    $table->integer('step_order')->nullable();
                }
                if (! Schema::hasColumn('crm_sequence_steps', 'delay_hours')) {
                    $table->integer('delay_hours')->nullable();
                }
                if (! Schema::hasColumn('crm_sequence_steps', 'body_html')) {
                    $table->text('body_html')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_email_sequences') && Schema::hasColumn('crm_email_sequences', 'enabled')) {
            Schema::table('crm_email_sequences', function (Blueprint $table) {
                $table->dropColumn('enabled');
            });
        }
        if (Schema::hasTable('crm_sequence_steps')) {
            Schema::table('crm_sequence_steps', function (Blueprint $table) {
                foreach (['step_order', 'delay_hours', 'body_html'] as $col) {
                    if (Schema::hasColumn('crm_sequence_steps', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
