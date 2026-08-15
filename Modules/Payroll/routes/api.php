<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\Api\PayrollController;

Route::middleware(['auth:sanctum', 'session.security', 'role:hr-manager,accountant,finance-manager,manager,admin'])->group(function () {
    Route::get('payslips',                [PayrollController::class, 'index']);
    Route::post('generate',               [PayrollController::class, 'generate']);
    Route::post('payslips/approve-batch', [PayrollController::class, 'approveBatch']);
    Route::post('process-payment',        [PayrollController::class, 'processPayment']);
    Route::get('statistics',              [PayrollController::class, 'statistics']);
    Route::get('taxes/by-country',        [PayrollController::class, 'taxesByCountry']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/payroll')->group(function () {
    Route::post('ai/assist', [\Modules\Payroll\Http\Controllers\Api\PayrollAiAssistController::class, 'assist'])
        ->name('payroll.ai.assist');
});
