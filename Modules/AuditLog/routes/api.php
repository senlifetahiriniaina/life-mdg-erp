<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AuditLog\Http\Controllers\Api\AuditLogApiController;
use Modules\AuditLog\Http\Controllers\Api\AuditLogAiAssistController;

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1')->group(function () {

    // ─── Audit Logs ────────────────────────────────────────────────────────────
    Route::get('audit-logs', [AuditLogApiController::class, 'index'])
        ->name('audit-logs.index');

    Route::get('audit-logs/stats', [AuditLogApiController::class, 'stats'])
        ->name('audit-logs.stats');

    Route::get('audit-logs/export', [AuditLogApiController::class, 'export'])
        ->name('audit-logs.export');

    Route::get('audit-logs/{id}', [AuditLogApiController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('audit-logs.show');

    // ── AI Assisted First — Contextual AI guidance ────────────────────────────
    Route::post('audit-logs/ai/assist', [AuditLogAiAssistController::class, 'assist'])
        ->name('auditlog.ai.assist');
});
