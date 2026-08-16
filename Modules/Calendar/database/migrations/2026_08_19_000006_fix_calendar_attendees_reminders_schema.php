<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * calendar_attendees/calendar_reminders were created with a calendar_event_id
 * FK, but every model/relation/factory/service in this module writes/reads
 * event_id — the whole internal event stack 500s on that mismatch. Also adds
 * calendar_attendees.is_organizer (real column CalendarService/
 * ICalExportService/CalendarController already read/write) and renames
 * calendar_reminders.reminder_type -> method + adds user_id (CalendarService
 * ::syncReminders() scopes reminders per event+user, column never existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('calendar_attendees', 'calendar_event_id') && ! Schema::hasColumn('calendar_attendees', 'event_id')) {
            Schema::table('calendar_attendees', function (Blueprint $table) {
                $table->renameColumn('calendar_event_id', 'event_id');
            });
        }

        if (! Schema::hasColumn('calendar_attendees', 'is_organizer')) {
            Schema::table('calendar_attendees', function (Blueprint $table) {
                $table->boolean('is_organizer')->default(false)->after('status');
            });
        }

        if (Schema::hasColumn('calendar_reminders', 'calendar_event_id') && ! Schema::hasColumn('calendar_reminders', 'event_id')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->renameColumn('calendar_event_id', 'event_id');
            });
        }

        if (Schema::hasColumn('calendar_reminders', 'reminder_type') && ! Schema::hasColumn('calendar_reminders', 'method')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->renameColumn('reminder_type', 'method');
            });
        }

        if (! Schema::hasColumn('calendar_reminders', 'user_id')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('calendar_attendees', 'event_id') && ! Schema::hasColumn('calendar_attendees', 'calendar_event_id')) {
            Schema::table('calendar_attendees', function (Blueprint $table) {
                $table->renameColumn('event_id', 'calendar_event_id');
            });
        }

        if (Schema::hasColumn('calendar_attendees', 'is_organizer')) {
            Schema::table('calendar_attendees', function (Blueprint $table) {
                $table->dropColumn('is_organizer');
            });
        }

        if (Schema::hasColumn('calendar_reminders', 'user_id')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('calendar_reminders', 'method') && ! Schema::hasColumn('calendar_reminders', 'reminder_type')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->renameColumn('method', 'reminder_type');
            });
        }

        if (Schema::hasColumn('calendar_reminders', 'event_id') && ! Schema::hasColumn('calendar_reminders', 'calendar_event_id')) {
            Schema::table('calendar_reminders', function (Blueprint $table) {
                $table->renameColumn('event_id', 'calendar_event_id');
            });
        }
    }
};
