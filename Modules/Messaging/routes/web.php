<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'module:Messaging'])->group(function () {
    Route::get('/messaging', fn () => Inertia::render('Messaging/Index'))->name('messaging.index');
});
