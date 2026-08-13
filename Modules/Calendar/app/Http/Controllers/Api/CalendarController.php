<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Services\CalendarService;
use Modules\Calendar\Services\ICalExportService;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly ICalExportService $iCalExportService,
    ) {}

    // -----------------------------------------------------------------------
    // Calendars
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/calendars
     * List all calendars belonging to the authenticated user.
     */
    public function indexCalendars(Request $request): JsonResponse
    {
        $calendars = $this->calendarService->getUserCalendars($request->user()->id);

        return response()->json($calendars);
    }

    /**
     * POST /api/v1/calendar/calendars
     * Create a new calendar.
     */
    public function storeCalendar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'type'       => 'nullable|in:personal,shared,module',
            'is_visible' => 'nullable|boolean',
        ]);

        $calendar = $this->calendarService->createCalendar($request->user()->id, $data);

        return response()->json($calendar, Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/calendar/calendars/{calendar}
     * Update a calendar.
     */
    public function updateCalendar(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('update', $calendar);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'color'      => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_visible' => 'sometimes|boolean',
        ]);

        $calendar = $this->calendarService->updateCalendar($calendar, $data);

        return response()->json($calendar);
    }

    /**
     * DELETE /api/v1/calendar/calendars/{calendar}
     * Delete a calendar and all its events.
     */
    public function destroyCalendar(Calendar $calendar): JsonResponse
    {
        $this->authorize('delete', $calendar);

        $this->calendarService->deleteCalendar($calendar);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // -----------------------------------------------------------------------
    // Events
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/events
     * List events across user's calendars within a date range.
     *
     * Query params:
     *   start        (required) ISO date/datetime
     *   end          (required) ISO date/datetime
     *   calendar_ids[]  optional filter
     *   modules[]       optional module_type filter
     */
    public function indexEvents(Request $request): JsonResponse
    {
        $request->validate([
            'start'           => 'required|date',
            'end'             => 'required|date|after_or_equal:start',
            'calendar_ids'    => 'nullable|array',
            'calendar_ids.*'  => 'integer',
            'modules'         => 'nullable|array',
            'modules.*'       => 'string',
        ]);

        $events = $this->calendarService->getEvents(
            userId: $request->user()->id,
            start: Carbon::parse($request->start),
            end: Carbon::parse($request->end),
            calendarIds: $request->input('calendar_ids', []),
            moduleTypes: $request->input('modules'),
        );

        return response()->json($events->map(fn (CalendarEvent $e) => $this->formatEvent($e)));
    }

    /**
     * POST /api/v1/calendar/events
     * Create a new event.
     */
    public function storeEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'calendar_id'                => 'required|integer|exists:calendar_calendars,id',
            'title'                      => 'required|string|max:255',
            'description'                => 'nullable|string',
            'start_at'                   => 'required|date',
            'end_at'                     => 'required|date|after_or_equal:start_at',
            'all_day'                    => 'nullable|boolean',
            'location'                   => 'nullable|string|max:255',
            'url'                        => 'nullable|url',
            'recurrence_rule'            => 'nullable|string|max:500',
            'recurrence_exception_dates' => 'nullable|array',
            'status'                     => 'nullable|in:confirmed,tentative,cancelled',
            'visibility'                 => 'nullable|in:public,private',
            'color'                      => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'module_type'                => 'nullable|string|max:100',
            'module_id'                  => 'nullable|integer',
            'attendees'                  => 'nullable|array',
            'attendees.*.email'          => 'required_with:attendees|email',
            'attendees.*.name'           => 'nullable|string|max:100',
            'attendees.*.user_id'        => 'nullable|integer',
            'attendees.*.is_organizer'   => 'nullable|boolean',
            'reminders'                  => 'nullable|array',
            'reminders.*.minutes_before' => 'required_with:reminders|integer|min:0',
            'reminders.*.method'         => 'required_with:reminders|in:email,push,popup',
        ]);

        $event = $this->calendarService->createEvent($request->user()->id, $data);

        return response()->json($this->formatEvent($event), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/calendar/events/{event}
     * Update an event.
     */
    public function updateEvent(Request $request, CalendarEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_at'    => 'sometimes|date',
            'end_at'      => 'sometimes|date',
            'all_day'     => 'nullable|boolean',
            'location'    => 'nullable|string|max:255',
            'status'      => 'nullable|in:confirmed,tentative,cancelled',
            'visibility'  => 'nullable|in:public,private',
            'color'       => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'attendees'   => 'nullable|array',
            'reminders'   => 'nullable|array',
        ]);

        $event = $this->calendarService->updateEvent($event, $data);

        return response()->json($this->formatEvent($event));
    }

    /**
     * DELETE /api/v1/calendar/events/{event}
     * Delete (soft-delete) an event.
     */
    public function destroyEvent(CalendarEvent $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->calendarService->deleteEvent($event);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * GET /api/v1/calendar/events/{event}/attendees
     * List event attendees.
     */
    public function attendees(CalendarEvent $event): JsonResponse
    {
        return response()->json($event->attendees()->get());
    }

    /**
     * POST /api/v1/calendar/events/{event}/attendees
     * Add an attendee to an event.
     */
    public function addAttendee(Request $request, CalendarEvent $event): JsonResponse
    {
        $data = $request->validate([
            'email'        => 'required|email',
            'name'         => 'nullable|string|max:100',
            'user_id'      => 'nullable|integer',
            'is_organizer' => 'nullable|boolean',
        ]);

        $attendee = $this->calendarService->addAttendee($event, $data);

        return response()->json($attendee, Response::HTTP_CREATED);
    }

    // -----------------------------------------------------------------------
    // iCal Export
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/export/ics
     * Download all user events as an .ics file.
     */
    public function exportIcs(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $userId = $request->user()->id;

        $events = CalendarEvent::with(['attendees', 'reminders'])
            ->whereHas('calendar', fn ($q) => $q->where('user_id', $userId))
            ->where('start_at', '>=', now()->subMonth())
            ->orderBy('start_at')
            ->get();

        $icsContent = $this->iCalExportService->exportToIcs($events, 'WideHalo - Mon Calendrier');

        return response()->streamDownload(
            fn () => print($icsContent),
            'widehalo-calendar.ics',
            ['Content-Type' => 'text/calendar; charset=utf-8'],
        );
    }

    // -----------------------------------------------------------------------
    // Upcoming mini-widget
    // -----------------------------------------------------------------------

    /**
     * GET /api/v1/calendar/upcoming
     * Return next 5 events for the mini-widget.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $events = $this->calendarService->getUpcoming($request->user()->id, 5);

        return response()->json($events->map(fn (CalendarEvent $e) => $this->formatEvent($e)));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function formatEvent(CalendarEvent $event): array
    {
        return [
            'id'              => $event->id,
            'calendar_id'     => $event->calendar_id,
            'title'           => $event->title,
            'description'     => $event->description,
            'start_at'        => $event->start_at->toIso8601String(),
            'end_at'          => $event->end_at->toIso8601String(),
            'all_day'         => $event->all_day,
            'location'        => $event->location,
            'url'             => $event->url,
            'recurrence_rule' => $event->recurrence_rule,
            'status'          => $event->status,
            'visibility'      => $event->visibility,
            'source'          => $event->source,
            'module_type'     => $event->module_type,
            'module_id'       => $event->module_id,
            'color'           => $event->color ?? $event->calendar?->color,
            'attendees'       => $event->relationLoaded('attendees') ? $event->attendees : [],
            'reminders'       => $event->relationLoaded('reminders') ? $event->reminders : [],
        ];
    }
}
