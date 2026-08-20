<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Web\AdminWebController;
use App\Http\Controllers\Web\DashboardWebController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect root to dashboard (Inertia app handles auth)
Route::get('/', function () {
    return redirect('/dashboard');
});

// Dashboard 360° route — injected with server-side metrics + AI insights
Route::get('/dashboard', [DashboardWebController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

// Notifications list (NotificationBell.vue's "Voir toutes les notifications" link)
// — self-fetching page, calls the real Modules\Core\NotificationController API.
Route::get('/notifications', fn () => Inertia::render('Notifications/Index'))
    ->middleware(['auth'])
    ->name('notifications.index');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');

    Route::get('/import', fn () => Inertia::render('Import/Index'))->name('import.index');

    // Admin panel routes (access-controlled inside controller constructor)
    Route::prefix('admin')->group(function () {
        Route::get('/', [AdminWebController::class, 'index'])->name('admin.index');
        Route::get('/servers', [AdminWebController::class, 'servers'])->name('admin.servers');
        Route::get('/backups', [AdminWebController::class, 'backups'])->name('admin.backups');
        Route::get('/users', [AdminWebController::class, 'users'])->name('admin.users');
        Route::get('/audit', [AdminWebController::class, 'audit'])->name('admin.audit');
        Route::get('/exchanges', [AdminWebController::class, 'exchanges'])->name('admin.exchanges');
        Route::get('/sandboxes', [AdminWebController::class, 'sandboxes'])->name('admin.sandboxes');
    });
});
