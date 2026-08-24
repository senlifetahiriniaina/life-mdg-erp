<?php

use Illuminate\Support\Facades\Route;
use Modules\Security\Http\Controllers\Web\SecurityWebController;

// Chantier 32.3: added role: to match the API gate added in the same
// chantier — every underlying axios call this page makes now 403s for
// anyone but security-admin/admin/super-admin anyway (each degrades
// gracefully via its own .catch()), but reaching the page itself was open
// to any authenticated user before this.
Route::middleware(['auth', 'module:Security', 'role:security-admin,admin,super-admin'])->group(function () {
    Route::get('/security', [SecurityWebController::class, 'index'])->name('security.index');
});
