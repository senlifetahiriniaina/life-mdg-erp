<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
     *
     * Chantier 32.12: wrapped in `{data: [...]}` — was a bare JSON array,
     * which every real Vue consumer (Index.vue's sidebar, Event/Create.vue's
     * calendar picker) reads as `response.data ?? []`, following this app's
     * dominant Laravel-resource-collection convention elsewhere. Confirmed
     * empirically this meant both pages have never shown a single real
     * calendar — the sidebar list was always empty and the Create-event
     * calendar `<select>` never had any options, so `calendar_id` stayed
     * `''` and every real event creation 422'd on `required|integer`.
     */
    public function indexCalendars(Request $request): JsonResponse
    {
        $calendars = $this->calendarService->getUserCalendars($request->user()->id);

        return response()->json(['data' => $calendars]);
    }

    /**
     * POST /api/v1/calendar/calendars
     * Create a new calendar.
     */
    public function storeCalendar(Request $request): JsonResponse
    {
        // Chantier 32.12: CalendarPolicy::create() is unconditionally true
        // today (so this was behaviorally a no-op), but every other
        // mutation on this controller calls authorize() at its point of
        // mutation per this session's established RBAC discipline — added
        // for consistency/defense-in-depth, matching create()'s already-
        // existing ability that nothing was actually calling.
        $this->authorize('create', Calendar::class);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'type'       => 'nullable|in:personal,shared,module',
            'is_visible' => 'nullable|boolean',
        ]);

        $calendar = $this->calendarService->createCalendar(
            $request->user()->id,
            $data,
            $request->user()->company_id,
        );

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
     *   start        optional ISO date/datetime — defaults to 2 months ago
     *   end          optional ISO date/datetime — defaults to 6 months ahead
     *   calendar_ids[]  optional filter
     *   modules[]       optional module_type filter
     *
     * Chantier 32.12: `start`/`end` used to be `required` — but the two real
     * Vue consumers (Index.vue's month grid, Teams.vue's week grid) both
     * call `GET events?per_page=200` with no date params at all, confirmed
     * empirically to 422 on every single real page load ("The start field
     * is required."), silently swallowed by both pages' own
     * `.catch(() => ({data: []}))`. Neither page re-fetches when the user
     * navigates to a different month/week either (a real, documented UX
     * limitation, not fixed here — see this chantier's CLAUDE.md entry),
     * so the fix is a broad-enough default window rather than requiring
     * the caller to always know a precise range. Response wrapped in
     * `{data: [...]}` for the same reason as indexCalendars() above.
     */
    public function indexEvents(Request $request): JsonResponse
    {
        $request->validate([
            'start'           => 'nullable|date',
            'end'             => 'nullable|date|after_or_equal:start',
            'calendar_ids'    => 'nullable|array',
            'calendar_ids.*'  => 'integer',
            'modules'         => 'nullable|array',
            'modules.*'       => 'string',
        ]);

        $start = $request->filled('start') ? Carbon::parse($request->start) : now()->subMonths(2)->startOfDay();
        $end   = $request->filled('end') ? Carbon::parse($request->end) : now()->addMonths(6)->endOfDay();

        $events = $this->calendarService->getEvents(
            userId: $request->user()->id,
            start: $start,
            end: $end,
            calendarIds: $request->input('calendar_ids', []),
            moduleTypes: $request->input('modules'),
        );

        return response()->json(['data' => $events->map(fn (CalendarEvent $e) => $this->formatEvent($e))]);
    }

    /**
     * GET /api/v1/calendar/events/{event}
     * Show a single event. Event/Show.vue self-fetches this — the method
     * never existed at all despite the page calling it.
     */
    public function showEvent(CalendarEvent $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->load(['attendees', 'reminders']);

        return response()->json($this->formatEvent($event));
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

        $event = $this->calendarService->createEvent(
            $request->user()->id,
            $data,
            $request->user()->company_id,
        );

        return response()->json($this->formatEvent($event), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/calendar/events/{event}
     * Update an event.
     *
     * Chantier 32.12: unlike storeEvent()'s `end_at => after_or_equal:start_at`,
     * this had no cross-field date constraint at all — confirmed empirically
     * that a PUT sending only `end_at` (a real partial-update shape, e.g.
     * dragging just the end handle of an event) could silently set it
     * before the event's own existing `start_at` with a 200 OK. A plain
     * `after_or_equal:start_at` rule wouldn't have been enough either: it
     * only evaluates when `start_at` is ALSO present in the same request
     * (Laravel skips comparison rules whose reference field is absent from
     * the input), so a real partial update sending only `end_at` would
     * still have bypassed it. Fixed with an `after()` hook that resolves
     * the effective start/end from whichever the request supplies, falling
     * back to the event's current stored values otherwise.
     */
    public function updateEvent(Request $request, CalendarEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validator = Validator::make($request->all(), [
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

        $validator->after(function ($validator) use ($request, $event) {
            $effectiveStart = $request->filled('start_at') ? Carbon::parse($request->input('start_at')) : $event->start_at;
            $effectiveEnd   = $request->filled('end_at') ? Carbon::parse($request->input('end_at')) : $event->end_at;

            if ($effectiveStart && $effectiveEnd && $effectiveEnd->lt($effectiveStart)) {
                $validator->errors()->add('end_at', 'La date de fin doit être postérieure ou égale à la date de début.');
            }
        });

        $data = $validator->validate();

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
        $this->authorize('view', $event);

        return response()->json($event->attendees()->get());
    }

    /**
     * POST /api/v1/calendar/events/{event}/attendees
     * Add an attendee to an event.
     *
     * Chantier 19 Lot 3: had zero authorize() call at all, unlike
     * updateEvent()/destroyEvent() on the same controller — any
     * authenticated user with Calendar-module access (i.e. every employee)
     * could add an attendee to any other user's event regardless of
     * ownership. Gated on the same 'update' ability updateEvent() already
     * uses, since adding an attendee is itself a mutation of the event.
     */
    public function addAttendee(Request $request, CalendarEvent $event): JsonResponse
    {
        $this->authorize('update', $event);

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
     *
     * Chantier 32.12: confirmed via a repo-wide grep that this endpoint has
     * zero real Vue consumer anywhere (the "mini-widget" it was built for
     * was never mounted) — wrapped in `{data: [...]}` anyway for
     * consistency with indexEvents()/indexCalendars() above, so a future
     * consumer doesn't inherit the same bare-array mismatch this chantier
     * found and fixed on the other 2 endpoints.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $events = $this->calendarService->getUpcoming($request->user()->id, 5);

        return response()->json(['data' => $events->map(fn (CalendarEvent $e) => $this->formatEvent($e))]);
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
