<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Chantier 8.3: Modules/Payroll had no routes/web.php at all — its real,
// self-fetching Dashboard/Index.vue page (payslips/statistics/tax
// breakdown/approve/process-payment, all calling the already-real payroll
// API) had no route anywhere in the app to reach it.
//
// Chantier 19 Lot 2: this had only ['auth'] — no module:Payroll/role: gate
// at all, unlike its own sibling routes/api.php (which correctly requires
// module:Payroll + role:hr-manager,payroll-officer,accountant,finance-
// manager,manager,admin), and unlike several other modules' web.php
// (Achats/Strategy) that gate the same way as their API. Not a data leak on
// its own — every real payslip/statistics/tax fetch this Dashboard page
// makes already goes through the correctly-RBAC'd API — but any
// authenticated user of any role could reach the staff payroll dashboard
// shell regardless of permission, a real inconsistency worth closing since
// this page is staff-only (bulk generate/approve/process-payment across
// every employee), not the separate employee self-service payslip view
// (which has no web page of its own — API-only via me/payslips).
Route::middleware(['auth', 'module:Payroll', 'role:hr-manager,payroll-officer,accountant,finance-manager,manager,admin'])->group(function () {
    Route::get('/', fn () => Inertia::render('Payroll/Dashboard/Index'))->name('dashboard');
});
