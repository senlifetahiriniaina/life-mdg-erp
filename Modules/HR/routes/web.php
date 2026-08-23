<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\HR\Http\Controllers\Web\EmployeeWebController;
use Modules\HR\Http\Controllers\Web\LeaveAnalyticsWebController;

// Chantier 32: this whole group had no role gate at all (unlike
// Modules/HR/routes/api.php, which already gates on the same role list) —
// any authenticated user of any role could reach every HR admin web page.
// Matches the module:X + role:... shape already proven at
// Modules/Achats/routes/web.php.
Route::middleware(['auth', 'module:HR', 'role:employee,hr-manager,payroll-officer,manager,admin'])->group(function () {
    // Chantier 8.3: this rendered a Blade view (hr::dashboard) that never
    // existed anywhere under Modules/HR/resources/views — GET /hr threw
    // "View not found" on every request. HR/Dashboard.vue is a real,
    // self-fetching page (calls dashboard/dashboard-realtime/leaves) that
    // was sitting unused right next to this bug.
    Route::get('/', fn () => Inertia::render('HR/Dashboard'))->name('dashboard');

    Route::get('employees', [EmployeeWebController::class, 'index'])->name('employees.index');
    // Chantier 8.3: registered before employees/{employee} so "create" isn't
    // swallowed as an employee ID.
    Route::get('employees/create', [EmployeeWebController::class, 'create'])->name('employees.create');
    Route::get('employees/{employee}/edit', [EmployeeWebController::class, 'edit'])->name('employees.edit');
    Route::get('employees/{employee}', [EmployeeWebController::class, 'show'])->name('employees.show');
    Route::get('payroll', [EmployeeWebController::class, 'payroll'])->name('payroll.index');
    Route::get('attendance', [EmployeeWebController::class, 'attendance'])->name('attendance.index');
    // Chantier 8.3: the module's real admin attendance-CRUD page was masked
    // by the personal clock-in page above (both resolved to the same
    // HR/Attendance/Index component name) — renamed to Manage and given its
    // own route instead of deleting real, working functionality.
    Route::get('attendance/manage', fn () => Inertia::render('HR/Attendance/Manage'))->name('attendance.manage');
    Route::get('shifts/schedule', [EmployeeWebController::class, 'schedule'])->name('shifts.schedule');
    Route::get('leave/analytics', [LeaveAnalyticsWebController::class, 'index'])->name('leave.analytics');
    Route::get('compensation', [EmployeeWebController::class, 'compensation'])->name('compensation.index');
    // Chantier 8.3: portal() was real (renders the real, working HR/Portal.vue
    // self-service page) but had no route pointing to it at all.
    Route::get('portal', [EmployeeWebController::class, 'portal'])->name('portal');
    // Chantier 8.3: both real, fully self-fetching pages with zero web route.
    Route::get('departments', fn () => Inertia::render('HR/Departments/Index'))->name('departments.index');
    Route::get('leaves', fn () => Inertia::render('HR/Leaves/Index'))->name('leaves.index');
});
