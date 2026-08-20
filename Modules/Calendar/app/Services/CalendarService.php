<?php

declare(strict_types=1);

namespace Modules\Calendar\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarAttendee;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Models\CalendarReminder;

class CalendarService
{
    // -----------------------------------------------------------------------
    // Calendar CRUD
    // -----------------------------------------------------------------------

    /**
     * Return all calendars visible to a given user.
     *
     * @return Collection<int, Calendar>
     */
    public function getUserCalendars(int $userId): Collection
    {
        return Calendar::where('user_id', $userId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new calendar for a user.
     *
     * Chantier 19 Lot 3: `tenant_id` used to come from `$data['tenant_id']`
     * — but neither `storeCalendar()`'s nor `storeEvent()`'s validation
     * rules ever accepted a `tenant_id` field, so this was always `null` on
     * every real calendar/event ever created, which in turn made
     * `CalendarEventPolicy::view()`'s "always true" design a real,
     * confirmed (empirically, via a real cross-company HTTP request)
     * cross-tenant leak: any authenticated employee of any company could
     * read any other company's calendar event — title, description,
     * location, attendee list — just by requesting `GET
     * calendar/events/{id}` with a guessed/enumerated id. Fixed by
     * threading the real `company_id` explicitly from the controller
     * (never trusting a client-supplied value) and scoping
     * `CalendarEventPolicy::view()`/`viewAny()` to same-company (see that
     * policy's own docblock).
     *
     * @param  array<string,mixed> $data
     */
    public function createCalendar(int $userId, array $data, ?int $companyId = null): Calendar
    {
        // Ensure first calendar is primary
        $hasPrimary = Calendar::where('user_id', $userId)->where('is_primary', true)->exists();

        return Calendar::create([
            'user_id'    => $userId,
            'tenant_id'  => $companyId,
            'name'       => $data['name'],
            'color'      => $data['color'] ?? '#3B82F6',
            'type'       => $data['type'] ?? 'personal',
            'source'     => $data['source'] ?? 'local',
            'is_primary' => $data['is_primary'] ?? ! $hasPrimary,
            'is_visible' => $data['is_visible'] ?? true,
        ]);
    }

    /**
     * Update an existing calendar.
     *
     * @param  array<string,mixed> $data
     */
    public function updateCalendar(Calendar $calendar, array $data): Calendar
    {
        $calendar->update(array_filter($data, fn ($v) => $v !== null));

        return $calendar->fresh();
    }

    public function deleteCalendar(Calendar $calendar): void
    {
        // Soft-delete all events first
        $calendar->events()->delete();
        $calendar->delete();
    }

    // -----------------------------------------------------------------------
    // Event CRUD
    // -----------------------------------------------------------------------

    /**
     * List events across one or more calendars within a date range.
     *
     * @param  int[]   $calendarIds
     * @param  string[]|null $moduleTypes  filter by module source
     */
    public function getEvents(
        int $userId,
        Carbon $start,
        Carbon $end,
        array $calendarIds = [],
        ?array $moduleTypes = null,
    ): Collection {
        $query = CalendarEvent::with(['calendar', 'attendees', 'reminders'])
            ->whereHas('calendar', fn ($q) => $q->where('user_id', $userId)->where('is_visible', true))
            ->where('start_at', '<=', $end)
            ->where('end_at', '>=', $start)
            ->whereNull('deleted_at');

        if (! empty($calendarIds)) {
            $query->whereIn('calendar_id', $calendarIds);
        }

        if ($moduleTypes !== null) {
            $query->whereIn('module_type', $moduleTypes);
        }

        return $query->orderBy('start_at')->get();
    }

    /**
     * Create a new event. See createCalendar()'s docblock for why
     * `$companyId` is threaded explicitly rather than read from `$data`.
     *
     * @param  array<string,mixed> $data
     */
    public function createEvent(int $userId, array $data, ?int $companyId = null): CalendarEvent
    {
        $event = CalendarEvent::create([
            'calendar_id'                 => $data['calendar_id'],
            'tenant_id'                   => $companyId,
            'title'                       => $data['title'],
            'description'                 => $data['description'] ?? null,
            'start_at'                    => $data['start_at'],
            'end_at'                      => $data['end_at'],
            'all_day'                     => $data['all_day'] ?? false,
            'location'                    => $data['location'] ?? null,
            'url'                         => $data['url'] ?? null,
            'recurrence_rule'             => $data['recurrence_rule'] ?? null,
            'recurrence_exception_dates'  => $data['recurrence_exception_dates'] ?? null,
            'status'                      => $data['status'] ?? 'confirmed',
            'visibility'                  => $data['visibility'] ?? 'public',
            'source'                      => 'local',
            'module_type'                 => $data['module_type'] ?? null,
            'module_id'                   => $data['module_id'] ?? null,
            'color'                       => $data['color'] ?? null,
            'created_by'                  => $userId,
        ]);

        // Add attendees
        if (! empty($data['attendees'])) {
            $this->syncAttendees($event, $data['attendees']);
        }

        // Add reminders
        if (! empty($data['reminders'])) {
            $this->syncReminders($event, $userId, $data['reminders']);
        }

        return $event->load(['attendees', 'reminders']);
    }

    /**
     * Update an event and its attendees/reminders.
     *
     * @param  array<string,mixed> $data
     */
    public function updateEvent(CalendarEvent $event, array $data): CalendarEvent
    {
        $event->update(array_filter($data, fn ($v, $k) => ! in_array($k, ['attendees', 'reminders']), ARRAY_FILTER_USE_BOTH));

        if (isset($data['attendees'])) {
            $this->syncAttendees($event, $data['attendees']);
        }

        if (isset($data['reminders'])) {
            $this->syncReminders($event, $event->created_by ?? 0, $data['reminders']);
        }

        return $event->fresh(['attendees', 'reminders']);
    }

    public function deleteEvent(CalendarEvent $event): void
    {
        $event->attendees()->delete();
        $event->reminders()->delete();
        $event->delete();
    }

    // -----------------------------------------------------------------------
    // Attendees
    // -----------------------------------------------------------------------

    /**
     * Sync attendees for an event (replace all).
     *
     * @param  array<int, array<string,mixed>> $attendees
     */
    public function syncAttendees(CalendarEvent $event, array $attendees): void
    {
        $event->attendees()->delete();

        foreach ($attendees as $attendee) {
            CalendarAttendee::create([
                'event_id'     => $event->id,
                'user_id'      => $attendee['user_id'] ?? null,
                'email'        => $attendee['email'],
                'name'         => $attendee['name'] ?? null,
                'status'       => $attendee['status'] ?? 'needs-action',
                'is_organizer' => $attendee['is_organizer'] ?? false,
            ]);
        }
    }

    /**
     * Add a single attendee.
     *
     * @param  array<string,mixed> $data
     */
    public function addAttendee(CalendarEvent $event, array $data): CalendarAttendee
    {
        return CalendarAttendee::create([
            'event_id'     => $event->id,
            'user_id'      => $data['user_id'] ?? null,
            'email'        => $data['email'],
            'name'         => $data['name'] ?? null,
            'status'       => $data['status'] ?? 'needs-action',
            'is_organizer' => $data['is_organizer'] ?? false,
        ]);
    }

    // -----------------------------------------------------------------------
    // Reminders
    // -----------------------------------------------------------------------

    /**
     * Sync reminders for an event+user (replace all).
     *
     * @param  array<int, array<string,mixed>> $reminders
     */
    public function syncReminders(CalendarEvent $event, int $userId, array $reminders): void
    {
        $event->reminders()->where('user_id', $userId)->delete();

        foreach ($reminders as $reminder) {
            CalendarReminder::create([
                'event_id'      => $event->id,
                'user_id'       => $userId,
                'minutes_before' => $reminder['minutes_before'] ?? 15,
                'method'        => $reminder['method'] ?? 'popup',
            ]);
        }
    }

    // -----------------------------------------------------------------------
    // Upcoming events helper
    // -----------------------------------------------------------------------

    /**
     * Return the next N upcoming events for a user (for mini-widget).
     *
     * @return Collection<int, CalendarEvent>
     */
    public function getUpcoming(int $userId, int $limit = 5): Collection
    {
        return CalendarEvent::with('calendar')
            ->whereHas('calendar', fn ($q) => $q->where('user_id', $userId)->where('is_visible', true))
            ->where('start_at', '>=', now())
            ->whereNull('deleted_at')
            ->orderBy('start_at')
            ->limit($limit)
            ->get();
    }
}
