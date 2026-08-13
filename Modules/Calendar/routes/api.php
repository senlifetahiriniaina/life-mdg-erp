<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Calendar\Http\Controllers\Api\CalendarController;
use Modules\Calendar\Http\Controllers\Api\CalendarSyncController;

/*
|--------------------------------------------------------------------------
| Calendar API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module:Calendar', 'role:employee,manager,admin'])->prefix('v1')->group(function () {

    // -----------------------------------------------------------------------
    // Calendars & Events (CRUD)
    // -----------------------------------------------------------------------
    Route::prefix('calendar')->group(function () {

        // Calendars
        Route::get('calendars', [CalendarController::class, 'indexCalendars'])->name('calendar.calendars.index');
        Route::post('calendars', [CalendarController::class, 'storeCalendar'])->name('calendar.calendars.store');
        Route::put('calendars/{calendar}', [CalendarController::class, 'updateCalendar'])->name('calendar.calendars.update');
        Route::delete('calendars/{calendar}', [CalendarController::class, 'destroyCalendar'])->name('calendar.calendars.destroy');

        // Events
        Route::get('events', [CalendarController::class, 'indexEvents'])->name('calendar.events.index');
        Route::post('events', [CalendarController::class, 'storeEvent'])->name('calendar.events.store');
        Route::put('events/{event}', [CalendarController::class, 'updateEvent'])->name('calendar.events.update');
        Route::delete('events/{event}', [CalendarController::class, 'destroyEvent'])->name('calendar.events.destroy');

        // Attendees
        Route::get('events/{event}/attendees', [CalendarController::class, 'attendees'])->name('calendar.events.attendees');
        Route::post('events/{event}/attendees', [CalendarController::class, 'addAttendee'])->name('calendar.events.attendees.store');

        // Upcoming (mini-widget)
        Route::get('upcoming', [CalendarController::class, 'upcoming'])->name('calendar.upcoming');

        // iCal export
        Route::get('export/ics', [CalendarController::class, 'exportIcs'])->name('calendar.export.ics');

        // -----------------------------------------------------------------------
        // Sync status
        // -----------------------------------------------------------------------
        Route::get('sync/status', [CalendarSyncController::class, 'status'])->name('calendar.sync.status');

        // Google
        Route::get('sync/google/auth', [CalendarSyncController::class, 'googleAuth'])->name('calendar.sync.google.auth');
        Route::get('sync/google/callback', [CalendarSyncController::class, 'googleCallback'])->name('calendar.sync.google.callback');
        Route::post('sync/google/sync', [CalendarSyncController::class, 'googleSync'])->name('calendar.sync.google.sync');
        Route::delete('sync/google/disconnect', [CalendarSyncController::class, 'googleDisconnect'])->name('calendar.sync.google.disconnect');

        // Outlook
        Route::get('sync/outlook/auth', [CalendarSyncController::class, 'outlookAuth'])->name('calendar.sync.outlook.auth');
        Route::get('sync/outlook/callback', [CalendarSyncController::class, 'outlookCallback'])->name('calendar.sync.outlook.callback');
        Route::post('sync/outlook/sync', [CalendarSyncController::class, 'outlookSync'])->name('calendar.sync.outlook.sync');
        Route::delete('sync/outlook/disconnect', [CalendarSyncController::class, 'outlookDisconnect'])->name('calendar.sync.outlook.disconnect');

        // Apple
        Route::post('sync/apple/connect', [CalendarSyncController::class, 'appleConnect'])->name('calendar.sync.apple.connect');
        Route::post('sync/apple/sync', [CalendarSyncController::class, 'appleSync'])->name('calendar.sync.apple.sync');
        Route::delete('sync/apple/disconnect', [CalendarSyncController::class, 'appleDisconnect'])->name('calendar.sync.apple.disconnect');
    });
});

// -----------------------------------------------------------------------
// Webhook endpoints — no auth:sanctum (provider-to-WideHalo callbacks)
// -----------------------------------------------------------------------
Route::prefix('v1/calendar/webhooks')->group(function () {
    Route::post('google', [CalendarSyncController::class, 'webhookGoogle'])->name('calendar.webhooks.google');
    Route::post('outlook', [CalendarSyncController::class, 'webhookOutlook'])->name('calendar.webhooks.outlook');
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('v1/calendar')->group(function () {
    Route::post('ai/assist', [\Modules\Calendar\Http\Controllers\Api\CalendarAiAssistController::class, 'assist'])
        ->name('calendar.ai.assist');
});
