<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\Api\PayrollController;

// Chantier 8.3 (Payroll): 'payroll-officer' — the role literally named for
// this module, seeded with full payroll.* permissions — was missing from
// this list entirely, locking it out of every payroll endpoint at the outer
// route gate before its permissions were ever checked.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'role:hr-manager,payroll-officer,accountant,finance-manager,manager,admin'])->group(function () {
    Route::get('payslips',                [PayrollController::class, 'index']);
    Route::post('generate',               [PayrollController::class, 'generate']);
    Route::post('payslips/approve-batch', [PayrollController::class, 'approveBatch']);
    Route::post('process-payment',        [PayrollController::class, 'processPayment']);
    Route::get('statistics',              [PayrollController::class, 'statistics']);
    Route::get('taxes/by-country',        [PayrollController::class, 'taxesByCountry']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->group(function () {
    Route::post('ai/assist', [\Modules\Payroll\Http\Controllers\Api\PayrollAiAssistController::class, 'assist'])
        ->name('payroll.ai.assist');
});
