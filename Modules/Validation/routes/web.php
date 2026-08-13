<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Web\ApprovalRequestController;

Route::middleware(['auth'])->group(function () {
    // Approval request dashboard and pages will be served by Inertia
    Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])->name('validation.requests.index');
    Route::get('/approval-requests/{request}', [ApprovalRequestController::class, 'show'])->name('validation.requests.show');
});
