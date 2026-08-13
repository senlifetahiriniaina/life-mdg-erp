<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('hd_tickets', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable()->index();
            }
            if (! Schema::hasColumn('hd_tickets', 'type')) {
                $table->string('type')->nullable();
            }
            if (! Schema::hasColumn('hd_tickets', 'satisfaction_score')) {
                $table->unsignedTinyInteger('satisfaction_score')->nullable();
            }
            if (! Schema::hasColumn('hd_tickets', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            foreach (['contact_id', 'type', 'satisfaction_score', 'first_response_at'] as $col) {
                if (Schema::hasColumn('hd_tickets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
