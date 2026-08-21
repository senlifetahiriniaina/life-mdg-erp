<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\Api\PayrollController;
use Modules\Payroll\Http\Controllers\Api\PayslipExportController;

// Chantier 8.3 (Payroll): 'payroll-officer' — the role literally named for
// this module, seeded with full payroll.* permissions — was missing from
// this list entirely, locking it out of every payroll endpoint at the outer
// route gate before its permissions were ever checked.
// Chantier 10: was missing module:Payroll entirely — every other module's
// route groups have it (HR, Timesheets, Projects, Helpdesk, ...); its
// absence here meant Payroll endpoints stayed reachable even if the module
// were ever disabled for a tenant via ModuleManager, unlike every sibling
// module. Added for consistency with the established pattern.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Payroll', 'role:hr-manager,payroll-officer,accountant,finance-manager,manager,admin'])->group(function () {
    Route::get('payslips',                [PayrollController::class, 'index']);
    Route::post('generate',               [PayrollController::class, 'generate']);
    Route::post('payslips/approve-batch', [PayrollController::class, 'approveBatch']);
    Route::post('process-payment',        [PayrollController::class, 'processPayment']);
    Route::get('statistics',              [PayrollController::class, 'statistics']);
    Route::get('taxes/by-country',        [PayrollController::class, 'taxesByCountry']);
});

// ── Employee self-service — view own payslips (any authenticated user,
// not just payroll staff; PayrollPolicy::view() enforces the "own record
// only" restriction for non-payroll-staff callers) ─────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Payroll'])->group(function () {
    Route::get('me/payslips',      [PayrollController::class, 'myPayslips']);
    Route::get('payslips/{payslip}', [PayrollController::class, 'show']);
    // Chantier 32.18: "bulletin de paie PDF" — Chantier 29's own report
    // proposal named this as the single most naturally-expected-but-missing
    // export in the app. Same route group / RBAC gate as show() above
    // (PayrollPolicy::view(), now company-scoped for staff, own-record-only
    // for an employee) since downloading a payslip is the same read access
    // as viewing it.
    Route::get('payslips/{payslip}/export/pdf', [PayslipExportController::class, 'pdf']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Payroll'])->group(function () {
    Route::post('ai/assist', [\Modules\Payroll\Http\Controllers\Api\PayrollAiAssistController::class, 'assist'])
        ->name('payroll.ai.assist');
});
