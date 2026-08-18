<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Calendar\Http\Controllers\Web\CalendarPageController;

/*
|--------------------------------------------------------------------------
| Calendar Web Routes (Inertia)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'module:Calendar'])->group(function () {
    Route::get('/calendar', [CalendarPageController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/settings', [CalendarPageController::class, 'settings'])->name('calendar.settings');

    // Chantier 8.6: 3 real, fully-built pages linked to from Calendar/Index.vue
    // and Calendar/Teams.vue with no route to reach them.
    Route::get('/calendar/events/create', fn () => \Inertia\Inertia::render('Calendar/Event/Create'))
        ->name('calendar.events.create');
    Route::get('/calendar/integrations', [CalendarPageController::class, 'integrations'])
        ->name('calendar.integrations');
    Route::get('/calendar/teams', [CalendarPageController::class, 'teams'])
        ->name('calendar.teams');

    // Event/Show.vue is real and self-fetching (GET /api/v1/calendar/events/{id},
    // added alongside the new showEvent() API method) but had no web route
    // either — wired up for the same URL-only-discoverability reason.
    Route::get('/calendar/events/{event}', fn () => \Inertia\Inertia::render('Calendar/Event/Show'))
        ->name('calendar.events.show');
});
