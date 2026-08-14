<?php

use Illuminate\Support\Facades\Route;
use Modules\Setup\Http\Controllers\Web\SetupWebController;

// Previously did not exist at all — SetupIndex.vue/SetupWizard.vue were
// never rendered by any route, real or otherwise.
Route::middleware(['auth'])->group(function () {
    Route::get('/setup', [SetupWebController::class, 'index'])->name('setup.index');
    Route::get('/setup/wizard', [SetupWebController::class, 'wizard'])->name('setup.wizard');
});
