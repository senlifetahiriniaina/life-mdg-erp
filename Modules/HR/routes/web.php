<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\Web\EmployeeWebController;
use Modules\HR\Http\Controllers\Web\LeaveAnalyticsWebController;

Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        return view('hr::dashboard');
    })->name('dashboard');

    Route::get('employees', [EmployeeWebController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}', [EmployeeWebController::class, 'show'])->name('employees.show');
    Route::get('payroll', [EmployeeWebController::class, 'payroll'])->name('payroll.index');
    Route::get('attendance', [EmployeeWebController::class, 'attendance'])->name('attendance.index');
    Route::get('shifts/schedule', [EmployeeWebController::class, 'schedule'])->name('shifts.schedule');
    Route::get('leave/analytics', [LeaveAnalyticsWebController::class, 'index'])->name('leave.analytics');
    Route::get('compensation', [EmployeeWebController::class, 'compensation'])->name('compensation.index');
});
