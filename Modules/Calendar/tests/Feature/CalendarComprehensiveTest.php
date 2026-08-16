<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarAttendee;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarReminder;
use Modules\Calendar\Models\CalendarSyncToken;
use Modules\Calendar\Services\CalendarService;
use Modules\Calendar\Services\ModuleEventAggregatorService;

uses(RefreshDatabase::class);

// ─── CalendarService — Calendars CRUD ────────────────────────────────────────

describe('CalendarService - Calendars CRUD', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(CalendarService::class);
    });

    test('can create a calendar for a user', function () {
        $calendar = $this->service->createCalendar($this->user->id, [
            'name'  => 'My Work Calendar',
            'color' => '#FF5722',
        ]);

        expect($calendar)->toBeInstanceOf(Calendar::class)
            ->and($calendar->name)->toBe('My Work Calendar');
    });

    test('can get calendars for a user', function () {
        $this->service->createCalendar($this->user->id, ['name' => 'Cal A']);
        $this->service->createCalendar($this->user->id, ['name' => 'Cal B']);

        $calendars = $this->service->getUserCalendars($this->user->id);

        expect($calendars->count())->toBeGreaterThanOrEqual(2);
    });

    test('can update a calendar', function () {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'Old Name']);

        $updated = $this->service->updateCalendar($calendar, ['name' => 'New Name']);

        expect($updated->name)->toBe('New Name');
    });

    test('can delete a calendar', function () {
        $calendar = $this->service->createCalendar($this->user->id, ['name' => 'To Delete']);
        $id       = $calendar->id;

        $this->service->deleteCalendar($calendar);

        expect(Calendar::find($id))->toBeNull();
    });
});

// ─── CalendarService — Events CRUD ───────────────────────────────────────────

describe('CalendarService - Events CRUD', function () {
    beforeEach(function () {
        $this->user     = actingAsUser('admin');
        $this->service  = app(CalendarService::class);
        $this->calendar = $this->service->createCalendar($this->user->id, ['name' => 'Test Cal']);
    });

    test('can create an event', function () {
        $event = $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'Team Meeting',
            'start_at'    => now()->toDateTimeString(),
            'end_at'      => now()->addHour()->toDateTimeString(),
        ]);

        expect($event)->toBeInstanceOf(CalendarEvent::class)
            ->and($event->title)->toBe('Team Meeting');
    });

    test('can get events in a date range', function () {
        $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'Event in Range',
            'start_at'    => now()->toDateTimeString(),
            'end_at'      => now()->addHour()->toDateTimeString(),
        ]);

        $events = $this->service->getEvents(
            $this->user->id,
            now()->subDay(),
            now()->addDay()
        );

        expect($events)->not->toBeEmpty();
    });

    test('can update an event', function () {
        $event = $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'Old Title',
            'start_at'    => now()->toDateTimeString(),
            'end_at'      => now()->addHour()->toDateTimeString(),
        ]);

        $updated = $this->service->updateEvent($event, ['title' => 'New Title']);

        expect($updated->title)->toBe('New Title');
    });

    test('can delete an event', function () {
        $event = $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'To Delete',
            'start_at'    => now()->toDateTimeString(),
            'end_at'      => now()->addHour()->toDateTimeString(),
        ]);
        $id = $event->id;

        $this->service->deleteEvent($event);

        expect(CalendarEvent::find($id))->toBeNull();
    });

    test('can get upcoming events for a user', function () {
        $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'Upcoming Event',
            'start_at'    => now()->addHour()->toDateTimeString(),
            'end_at'      => now()->addHours(2)->toDateTimeString(),
        ]);

        $upcoming = $this->service->getUpcoming($this->user->id, 5);

        expect($upcoming->count())->toBeGreaterThanOrEqual(1);
    });
});

// ─── CalendarService — Attendees ──────────────────────────────────────────────

describe('CalendarService - Attendees', function () {
    beforeEach(function () {
        $this->user     = actingAsUser('admin');
        $this->service  = app(CalendarService::class);
        $this->calendar = $this->service->createCalendar($this->user->id, ['name' => 'Cal']);
        $this->event    = $this->service->createEvent($this->user->id, [
            'calendar_id' => $this->calendar->id,
            'title'       => 'Meeting',
            'start_at'    => now()->toDateTimeString(),
            'end_at'      => now()->addHour()->toDateTimeString(),
        ]);
    });

    test('can add an attendee to an event', function () {
        $attendee = $this->service->addAttendee($this->event, [
            'email' => 'attendee@example.com',
            'name'  => 'John Doe',
            'rsvp'  => 'pending',
        ]);

        expect($attendee)->toBeInstanceOf(CalendarAttendee::class)
            ->and($attendee->email)->toBe('attendee@example.com');
    });

    test('can sync attendees for an event', function () {
        $this->service->syncAttendees($this->event, [
            ['email' => 'a@example.com', 'name' => 'Alice', 'rsvp' => 'accepted'],
            ['email' => 'b@example.com', 'name' => 'Bob',   'rsvp' => 'pending'],
        ]);

        expect($this->event->attendees()->count())->toBe(2);
    });
});

// ─── ModuleEventAggregatorService ────────────────────────────────────────────

describe('ModuleEventAggregatorService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ModuleEventAggregatorService::class);
    });

    test('service is resolvable from container', function () {
        $service = app(ModuleEventAggregatorService::class);
        expect($service)->toBeInstanceOf(ModuleEventAggregatorService::class);
    });

    test('can aggregate events for a user', function () {
        $count = $this->service->aggregateForUser($this->user->id);

        expect($count)->toBeInt()
            ->and($count)->toBeGreaterThanOrEqual(0);
    });

    test('can aggregate with specific sources filter', function () {
        $count = $this->service->aggregateForUser($this->user->id, ['HR', 'Projects']);

        expect($count)->toBeInt();
    });
});

// ─── API Endpoints — Calendars ────────────────────────────────────────────────

describe('Calendar API - Calendars', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list calendars', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/calendar/calendars')
            ->assertOk();
    });

    test('can create a calendar via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/calendar/calendars', [
                'name'  => 'API Calendar',
                'color' => '#2196F3',
            ])
            ->assertCreated();
    });

    test('can update a calendar via API', function () {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/calendar/calendars/{$calendar->id}", [
                'name' => 'Updated Calendar',
            ])
            ->assertOk();
    });

    test('can delete a calendar via API', function () {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/calendar/calendars/{$calendar->id}")
            ->assertNoContent();
    });

    test('unauthenticated user cannot access calendars', function () {
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/v1/calendar/calendars')
            ->assertUnauthorized();
    });
});

// ─── API Endpoints — Events ───────────────────────────────────────────────────

describe('Calendar API - Events', function () {
    beforeEach(function () {
        $this->user     = actingAsUser('admin');
        $this->calendar = Calendar::factory()->create(['user_id' => $this->user->id]);
    });

    test('can list events', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/calendar/events?' . http_build_query([
                'start' => now()->subDay()->toDateString(),
                'end'   => now()->addDay()->toDateString(),
            ]))
            ->assertOk();
    });

    test('can create an event via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/calendar/events', [
                'calendar_id' => $this->calendar->id,
                'title'       => 'API Event',
                'start_at'    => now()->toDateTimeString(),
                'end_at'      => now()->addHour()->toDateTimeString(),
            ])
            ->assertCreated();
    });

    test('can update an event via API', function () {
        $event = CalendarEvent::factory()->create([
            'calendar_id' => $this->calendar->id,
            'created_by'  => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/calendar/events/{$event->id}", [
                'title' => 'Updated Event',
            ])
            ->assertOk();
    });

    test('can delete an event via API', function () {
        $event = CalendarEvent::factory()->create([
            'calendar_id' => $this->calendar->id,
            'created_by'  => $this->user->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/calendar/events/{$event->id}")
            ->assertNoContent();
    });

    test('can get upcoming events via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/calendar/upcoming')
            ->assertOk();
    });

    test('can export events as iCal', function () {
        $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/calendar/export/ics')
            ->assertOk()
            ->assertHeader('Content-Type');
    });
});

// ─── API Endpoints — Attendees ────────────────────────────────────────────────

describe('Calendar API - Attendees', function () {
    beforeEach(function () {
        $this->user     = actingAsUser('admin');
        $this->calendar = Calendar::factory()->create(['user_id' => $this->user->id]);
        $this->event    = CalendarEvent::factory()->create([
            'calendar_id' => $this->calendar->id,
            'created_by'  => $this->user->id,
        ]);
    });

    test('can list attendees for an event', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/calendar/events/{$this->event->id}/attendees")
            ->assertOk();
    });

    test('can add an attendee via API', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/calendar/events/{$this->event->id}/attendees", [
                'email' => 'guest@example.com',
                'name'  => 'Guest User',
            ])
            ->assertCreated();
    });
});

// ─── Models ───────────────────────────────────────────────────────────────────

describe('Calendar Models', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('Calendar factory creates valid model', function () {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);
        expect($calendar->id)->not->toBeNull()
            ->and($calendar->name)->not->toBeEmpty();
    });

    test('CalendarEvent factory creates valid model', function () {
        $calendar = Calendar::factory()->create(['user_id' => $this->user->id]);
        $event    = CalendarEvent::factory()->create([
            'calendar_id' => $calendar->id,
            'created_by'  => $this->user->id,
        ]);
        expect($event->id)->not->toBeNull();
    });

    test('CalendarAttendee factory creates valid model', function () {
        $attendee = CalendarAttendee::factory()->create();
        expect($attendee->id)->not->toBeNull();
    });

    test('CalendarReminder factory creates valid model', function () {
        $reminder = CalendarReminder::factory()->create();
        expect($reminder->id)->not->toBeNull();
    });

    test('CalendarSyncToken factory creates valid model', function () {
        $token = CalendarSyncToken::factory()->create(['user_id' => $this->user->id]);
        expect($token->id)->not->toBeNull();
    });
});
