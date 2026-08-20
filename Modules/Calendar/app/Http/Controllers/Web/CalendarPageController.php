<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Calendar\Jobs\SyncCalendarJob;
use Modules\Calendar\Services\CalendarService;

class CalendarPageController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    /**
     * GET /calendar
     * Main calendar view — renders Calendar/Index.vue
     *
     * Chantier 19 Lot 3: `ModuleEventAggregatorService::aggregateForUser()`
     * (HR leaves/Project tasks/Helpdesk SLA/Strategy milestones/Accounting
     * deadlines/Workflow schedules/Timesheets — the cross-module Calendar
     * feature documented in this app's own history) was confirmed, by
     * grepping every real `SyncCalendarJob::dispatch()` call site, to be
     * entirely unreachable: `CalendarSyncController`'s google/outlook/apple
     * sync endpoints all dispatch the job with a specific provider
     * ('google'/'outlook'/'apple'), and `SyncCalendarJob::handle()` only
     * runs module aggregation when the provider is `null` or `'modules'` —
     * a combination nothing anywhere in this app ever dispatches. On top
     * of the wrong-table-name bugs already fixed in
     * ModuleEventAggregatorService itself, this meant the feature has
     * never populated a single real event. Wired up the simplest, most
     * natural trigger point: loading the calendar page queues a 'modules'
     * sync (via the same job/provider mechanism the other 3 providers
     * already use), so cross-module events populate without inventing a
     * new scheduled-task design.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Throttled to once per 5 minutes per user — CalendarEvent::updateOrCreate()
        // is idempotent so repeated dispatches are safe, but there's no reason
        // to re-run 7 source-module queries on every single page load.
        $throttleKey = "calendar_module_sync:{$user->id}";
        if (! Cache::has($throttleKey)) {
            Cache::put($throttleKey, true, now()->addMinutes(5));
            SyncCalendarJob::dispatch($user->id, 'modules');
        }

        $calendars = $this->calendarService->getUserCalendars($user->id);
        $upcoming  = $this->calendarService->getUpcoming($user->id, 5);

        return Inertia::render('Calendar/Index', [
            'calendars'      => $calendars,
            'upcomingCount'  => $upcoming->count(),
        ]);
    }

    /**
     * GET /calendar/settings
     * Calendar sync settings — Calendar/Settings.vue never existed anywhere
     * in the repo (this route 500'd on every visit). Calendar/Integrations.vue
     * already covers exactly this concept (Google/Outlook/Apple connect/
     * disconnect/sync status) and is a fully self-fetching page (it calls
     * GET sync/status itself, no server props needed) — so /calendar/settings
     * renders the same real page rather than duplicating it with a second,
     * near-identical component. /calendar/integrations (added alongside this
     * fix) renders the identical page under its own, more discoverable URL.
     */
    public function settings(): Response
    {
        return Inertia::render('Calendar/Integrations');
    }

    /**
     * GET /calendar/integrations
     * Alias of settings() under a more discoverable URL — see settings()'s
     * docblock. Kept as a distinct method (rather than reusing the route
     * name) so both URLs read naturally in route listings.
     */
    public function integrations(): Response
    {
        return Inertia::render('Calendar/Integrations');
    }

    /**
     * GET /calendar/teams
     * Team availability calendar — renders Calendar/Teams.vue, a real,
     * fully self-fetching page (GET hr/employees + GET calendar/events).
     */
    public function teams(): Response
    {
        return Inertia::render('Calendar/Teams');
    }
}
