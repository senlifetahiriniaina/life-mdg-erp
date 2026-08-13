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
});
