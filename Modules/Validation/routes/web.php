<?php

use Illuminate\Support\Facades\Route;
use Modules\Validation\Http\Controllers\Web\ApprovalRequestController;
use Modules\Validation\Http\Controllers\Web\ValidationRuleWebController;
use Modules\Validation\Http\Controllers\Web\WorkflowWebController;

Route::middleware(['auth'])->group(function () {
    // Approval request dashboard and pages will be served by Inertia
    Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])->name('validation.requests.index');
    Route::get('/approval-requests/{approval_request}', [ApprovalRequestController::class, 'show'])->name('validation.requests.show');

    Route::get('/workflows', [WorkflowWebController::class, 'index'])->name('validation.workflows.index');
    Route::get('/workflows/builder', [WorkflowWebController::class, 'create'])->name('validation.workflows.create');
    Route::get('/workflows/{workflow}/builder', [WorkflowWebController::class, 'builder'])->name('validation.workflows.builder');

    Route::get('/validation-rules', [ValidationRuleWebController::class, 'index'])->name('validation.rules.index');
});
