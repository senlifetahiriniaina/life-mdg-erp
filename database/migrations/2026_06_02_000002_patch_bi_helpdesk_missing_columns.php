<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // bi_reports — add user_id, type, is_scheduled
        if (Schema::hasTable('bi_reports')) {
            Schema::table('bi_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('bi_reports', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('bi_reports', 'type')) {
                    $table->string('type')->nullable();
                }
                if (!Schema::hasColumn('bi_reports', 'is_scheduled')) {
                    $table->boolean('is_scheduled')->default(false);
                }
                if (!Schema::hasColumn('bi_reports', 'description')) {
                    $table->text('description')->nullable();
                }
            });
        }

        // hd_ticket_comments — add ticket_id, user_id, body
        if (Schema::hasTable('hd_ticket_comments')) {
            Schema::table('hd_ticket_comments', function (Blueprint $table) {
                if (!Schema::hasColumn('hd_ticket_comments', 'ticket_id')) {
                    $table->unsignedBigInteger('ticket_id')->nullable()->index();
                }
                if (!Schema::hasColumn('hd_ticket_comments', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('hd_ticket_comments', 'body')) {
                    $table->text('body')->nullable();
                }
                if (!Schema::hasColumn('hd_ticket_comments', 'is_internal')) {
                    $table->boolean('is_internal')->default(false);
                }
            });
        }
    }

    public function down(): void {}
};
