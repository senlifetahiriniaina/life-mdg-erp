<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\Web\InvoiceWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return view('accounting::dashboard');
    })->name('dashboard');

    Route::get('invoices', [InvoiceWebController::class, 'index'])->name('invoices.index');
    Route::get('invoices/approvals', [InvoiceWebController::class, 'approvalQueue'])->name('invoices.approvals.index');
    Route::get('invoices/{invoice}/approval', [InvoiceWebController::class, 'showApproval'])->name('invoices.approval.show');
});
