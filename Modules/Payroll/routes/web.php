<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Chantier 8.3: Modules/Payroll had no routes/web.php at all — its real,
// self-fetching Dashboard/Index.vue page (payslips/statistics/tax
// breakdown/approve/process-payment, all calling the already-real payroll
// API) had no route anywhere in the app to reach it.
Route::middleware(['auth'])->group(function () {
    Route::get('/', fn () => Inertia::render('Payroll/Dashboard/Index'))->name('dashboard');
});
