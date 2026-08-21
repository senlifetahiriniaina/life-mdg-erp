<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Setup\Http\Controllers\Web\SetupWebController;

// Previously did not exist at all — SetupIndex.vue/SetupWizard.vue were
// never rendered by any route, real or otherwise.
Route::middleware(['auth'])->group(function () {
    Route::get('/setup', [SetupWebController::class, 'index'])->name('setup.index');
    Route::get('/setup/wizard', [SetupWebController::class, 'wizard'])->name('setup.wizard');

    // Chantier 32.10: real page for the previously-orphaned
    // DataImportController/AiDataImportService/ImportDataJob pipeline (see
    // that page's own docblock for the full "activate, don't delete"
    // reasoning) — self-fetching, no server props needed, matching the
    // established plain-closure precedent already used throughout this app
    // for self-fetch pages.
    Route::get('/setup/ai-import', fn () => Inertia::render('Setup/AiImport/Index'))->name('setup.ai-import');
});
