<?php

use Illuminate\Support\Facades\Route;
use Modules\AuditLog\Http\Controllers\Web\AuditLogWebController;

Route::middleware(['auth', 'module:AuditLog'])->group(function () {
    Route::get('/audit/logs', [AuditLogWebController::class, 'index'])->name('audit.logs.index');
});
