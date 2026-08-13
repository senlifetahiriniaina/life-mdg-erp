<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix wa_interactive_messages_sent table - add proper columns
        if (Schema::hasTable('wa_interactive_messages_sent')) {
            Schema::table('wa_interactive_messages_sent', function (Blueprint $table) {
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'conversation_id')) {
                    $table->unsignedBigInteger('conversation_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->after('conversation_id');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'direction')) {
                    $table->string('direction')->default('outbound')->after('template_id');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'type')) {
                    $table->string('type')->default('button')->after('direction');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'payload')) {
                    $table->json('payload')->nullable()->after('type');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'response_received')) {
                    $table->boolean('response_received')->default(false)->after('payload');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'response_payload')) {
                    $table->json('response_payload')->nullable()->after('response_received');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'sent_at')) {
                    $table->timestamp('sent_at')->nullable()->after('response_payload');
                }
                if (! Schema::hasColumn('wa_interactive_messages_sent', 'responded_at')) {
                    $table->timestamp('responded_at')->nullable()->after('sent_at');
                }
            });
        }
    }

    public function down(): void
    {
        // No rollback needed for column additions
    }
};
