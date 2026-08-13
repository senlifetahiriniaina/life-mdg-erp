<?php

use Illuminate\Support\Facades\Route;
use Modules\Projects\Http\Controllers\Web\ProjectTimeReportController;
use Modules\Projects\Http\Controllers\Web\ProjectWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth', 'module:Projects'])->group(function () {
    Route::get('/projects', [ProjectWebController::class, 'index'])->name('projects.index');
    Route::get('/projects/time-report', [ProjectTimeReportController::class, 'index'])->name('projects.time-report.index');
    Route::get('/projects/{project}', [ProjectWebController::class, 'show'])->name('projects.show');
});
