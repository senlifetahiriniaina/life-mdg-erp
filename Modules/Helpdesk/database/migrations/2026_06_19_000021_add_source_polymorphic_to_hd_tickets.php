<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic cross-module linking for tickets: any module can raise a ticket
 * against one of its own records via source_type/source_id (polymorphic),
 * instead of the previous unconnected contact_id/customer_id/source_ref
 * columns which had no matching relation on the Ticket model.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('hd_tickets', 'source_type')) {
                $table->string('source_type')->nullable()->after('source_ref');
            }
            if (! Schema::hasColumn('hd_tickets', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        Schema::table('hd_tickets', function (Blueprint $table) {
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hd_tickets')) {
            return;
        }

        Schema::table('hd_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('hd_tickets', 'source_type')) {
                $table->dropIndex(['source_type', 'source_id']);
                $table->dropColumn(['source_type', 'source_id']);
            }
        });
    }
};
