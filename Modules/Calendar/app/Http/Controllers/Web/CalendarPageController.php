<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Calendar\Services\CalendarService;

class CalendarPageController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    /**
     * GET /calendar
     * Main calendar view — renders Calendar/Index.vue
     */
    public function index(Request $request): Response
    {
        $user      = $request->user();
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
