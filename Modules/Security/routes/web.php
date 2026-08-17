<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\Web\SecurityWebController;

Route::middleware(['auth', 'module:Security'])->group(function () {
    Route::get('/security', [SecurityWebController::class, 'index'])->name('security.index');
});
