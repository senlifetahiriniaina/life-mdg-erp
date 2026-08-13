<?php

declare(strict_types=1);

namespace Modules\Calendar\Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Services\CalendarService;

class CalendarServiceTest extends TestCase
{
    private CalendarService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CalendarService::class);
        $this->user    = User::factory()->create();
    }

    /** @test */
    public function it_creates_a_calendar(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, [
            'name'  => 'Work Calendar',
            'color' => '#3B82F6',
            'type'  => 'personal',
        ]);

        $this->assertInstanceOf(Calendar::class, $calendar);
        $this->assertEquals('Work Calendar', $calendar->name);
        $this->assertEquals('#3B82F6', $calendar->color);
        $this->assertEquals($this->user->id, $calendar->user_id);
    }

    /** @test */
    public function first_calendar_is_set_as_primary(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'My Calendar']);

        $this->assertTrue($calendar->is_primary);
    }

    /** @test */
    public function subsequent_calendars_are_not_primary(): void
    {
        $this->service->createCalendar($this->user->id, ['name' => 'First']);
        $second = $this->service->createCalendar($this->user->id, ['name' => 'Second']);

        $this->assertFalse($second->is_primary);
    }

    /** @test */
    public function it_retrieves_user_calendars(): void
    {
        $this->service->createCalendar($this->user->id, ['name' => 'Cal 1']);
        $this->service->createCalendar($this->user->id, ['name' => 'Cal 2']);

        $otherUser = User::factory()->create();
        $this->service->createCalendar($otherUser->id, ['name' => 'Other Cal']);

        $calendars = $this->service->getUserCalendars($this->user->id);

        $this->assertCount(2, $calendars);
    }

    /** @test */
    public function it_updates_a_calendar(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Old Name']);
        $updated  = $this->service->updateCalendar($calendar, ['name' => 'New Name', 'color' => '#FF0000']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('#FF0000', $updated->color);
    }

    /** @test */
    public function it_deletes_a_calendar_and_its_events(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'To Delete']);

        $event = $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'Event to delete',
            'start_at'    => now()->addDay(),
            'end_at'      => now()->addDay()->addHour(),
        ]);

        $this->assertNotNull($event->id, 'Event should have been created');

        $calendarId = $calendar->id;
        $eventId    = $event->id;

        $this->service->deleteCalendar($calendar);

        // Event should no longer exist (soft-deleted or cascade-deleted)
        $this->assertDatabaseMissing('calendar_events', ['id' => $eventId, 'deleted_at' => null]);
        $this->assertDatabaseMissing('calendar_calendars', ['id' => $calendarId, 'deleted_at' => null]);
    }

    /** @test */
    public function it_creates_a_calendar_event(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Events Cal']);
        $start    = now()->addDay();
        $end      = $start->copy()->addHour();

        $event = $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'Team Meeting',
            'start_at'    => $start,
            'end_at'      => $end,
        ]);

        $this->assertInstanceOf(CalendarEvent::class, $event);
        $this->assertEquals('Team Meeting', $event->title);
        $this->assertEquals($calendar->id, $event->calendar_id);
    }

    /** @test */
    public function it_lists_events_in_date_range(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Range Cal']);

        $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'In Range',
            'start_at'    => now()->addDays(3),
            'end_at'      => now()->addDays(3)->addHour(),
        ]);
        $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'Out of Range',
            'start_at'    => now()->addDays(10),
            'end_at'      => now()->addDays(10)->addHour(),
        ]);

        $events = $this->service->getEvents(
            $this->user->id,
            now(),
            now()->addDays(7),
            [$calendar->id]
        );

        $this->assertCount(1, $events);
        $this->assertEquals('In Range', $events->first()->title);
    }

    /** @test */
    public function it_updates_a_calendar_event(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Update Cal']);
        $event    = $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'Old Title',
            'start_at'    => now()->addDay(),
            'end_at'      => now()->addDay()->addHour(),
        ]);

        $updated = $this->service->updateEvent($event, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
    }

    /** @test */
    public function it_deletes_a_calendar_event(): void
    {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Delete Cal']);
        $event    = $this->service->createEvent($this->user->id, [
            'calendar_id' => $calendar->id,
            'title'       => 'To Delete',
            'start_at'    => now()->addDay(),
            'end_at'      => now()->addDay()->addHour(),
        ]);

        $eventId = $event->id;
        $this->service->deleteEvent($event);

        $this->assertSoftDeleted('calendar_events', ['id' => $eventId]);
    }
}
