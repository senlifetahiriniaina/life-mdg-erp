<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (false) { // 'calendar_calendars' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('calendar_calendars', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('name', 100);
                $table->string('color', 7)->default('#3B82F6');
                $table->string('type', 20)->default('personal'); // personal|shared|module
                $table->string('source', 20)->default('local'); // local|google|outlook|apple
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_visible')->default(true);
                $table->string('sync_token')->nullable();
                $table->string('external_calendar_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (false) { // 'calendar_events' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('calendar_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('calendar_id')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->dateTime('start_at')->index();
                $table->dateTime('end_at')->index();
                $table->boolean('all_day')->default(false);
                $table->string('location')->nullable();
                $table->string('url')->nullable();
                $table->string('recurrence_rule')->nullable();
                $table->json('recurrence_exception_dates')->nullable();
                $table->string('status', 20)->default('confirmed'); // confirmed|tentative|cancelled
                $table->string('visibility', 20)->default('public'); // public|private
                $table->string('source', 20)->default('local'); // local|google|outlook|apple|module
                $table->string('external_event_id')->nullable();
                $table->string('external_etag')->nullable();
                $table->string('module_type')->nullable();
                $table->unsignedBigInteger('module_id')->nullable();
                $table->string('color', 7)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('calendar_id')->references('id')->on('calendar_calendars')->cascadeOnDelete();
            });
        }

        if (false) { // 'calendar_attendees' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('calendar_attendees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('email');
                $table->string('name');
                $table->string('status', 20)->default('needs-action'); // accepted|declined|tentative|needs-action
                $table->boolean('is_organizer')->default(false);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('calendar_events')->cascadeOnDelete();
            });
        }

        if (false) { // 'calendar_reminders' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('calendar_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedSmallInteger('minutes_before')->default(15);
                $table->string('method', 20)->default('popup'); // email|popup|sms
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('calendar_events')->cascadeOnDelete();
            });
        }

        if (false) { // 'calendar_sync_tokens' is now created by its module migration (canonical schema); root placeholder disabled
            Schema::create('calendar_sync_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('provider', 20); // google|outlook|apple
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->dateTime('token_expires_at')->nullable();
                $table->json('calendar_ids')->nullable();
                $table->dateTime('last_synced_at')->nullable();
                $table->json('sync_errors')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_tokens');
        Schema::dropIfExists('calendar_reminders');
        Schema::dropIfExists('calendar_attendees');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_calendars');
    }
};
