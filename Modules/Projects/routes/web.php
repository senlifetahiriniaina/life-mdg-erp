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
    Route::get('/projects/roadmap', [ProjectWebController::class, 'roadmap'])->name('projects.roadmap');
    Route::get('/projects/{project}', [ProjectWebController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/calendar', [ProjectWebController::class, 'calendar'])->name('projects.calendar');
    Route::get('/projects/{project}/gantt', [ProjectWebController::class, 'gantt'])->name('projects.gantt');
    Route::get('/projects/{project}/kanban', [ProjectWebController::class, 'kanban'])->name('projects.kanban');
    Route::get('/projects/{project}/automation', [ProjectWebController::class, 'automation'])->name('projects.automation');
    Route::get('/projects/{project}/epics', [ProjectWebController::class, 'epics'])->name('projects.epics');
    Route::get('/projects/{project}/sprints', [ProjectWebController::class, 'sprints'])->name('projects.sprints');
});
