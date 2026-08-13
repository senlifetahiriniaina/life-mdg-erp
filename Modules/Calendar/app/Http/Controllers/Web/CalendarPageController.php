<?php

declare(strict_types=1);

namespace Modules\Calendar\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Calendar\Models\CalendarSyncToken;
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
     * Calendar sync settings — renders Calendar/Settings.vue
     */
    public function settings(Request $request): Response
    {
        $user   = $request->user();
        $tokens = CalendarSyncToken::where('user_id', $user->id)
            ->get()
            ->keyBy('provider')
            ->map(fn ($token) => [
                'connected'      => true,
                'last_synced_at' => $token->last_synced_at?->diffForHumans(),
                'has_errors'     => ! empty($token->sync_errors),
            ]);

        return Inertia::render('Calendar/Settings', [
            'syncStatus' => [
                'google'  => $tokens->get('google', ['connected' => false]),
                'outlook' => $tokens->get('outlook', ['connected' => false]),
                'apple'   => $tokens->get('apple', ['connected' => false]),
            ],
        ]);
    }
}
