<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\CRM\Http\Controllers\Web\ContactWebController;
use Modules\CRM\Http\Controllers\Web\ForecastWebController;
use Modules\CRM\Http\Controllers\Web\OpportunityScoringWebController;
use Modules\CRM\Http\Controllers\Web\QuoteWebController;
use Modules\CRM\Http\Controllers\Web\TerritoryWebController;

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

// Inertia web routes for CRM module
Route::middleware(['auth', 'module:CRM'])->group(function () {
    Route::get('/crm/contacts', [ContactWebController::class, 'index'])->name('crm.contacts.index');
    Route::get('/crm/contacts/create', [ContactWebController::class, 'create'])->name('crm.contacts.create');
    Route::get('/crm/contacts/{contact}', [ContactWebController::class, 'show'])->name('crm.contacts.show');
    Route::get('/crm/leads', [ContactWebController::class, 'leads'])->name('crm.leads.index');
    Route::get('/crm/accounts', [ContactWebController::class, 'accounts'])->name('crm.accounts.index');
    Route::get('/crm/accounts/create', [ContactWebController::class, 'createAccount'])->name('crm.accounts.create');
    Route::post('/crm/accounts', [ContactWebController::class, 'storeAccount'])->name('crm.accounts.store');
    Route::get('/crm/accounts/{account}/edit', [ContactWebController::class, 'editAccount'])->name('crm.accounts.edit');
    Route::put('/crm/accounts/{account}', [ContactWebController::class, 'updateAccount'])->name('crm.accounts.update');
    Route::get('/crm/email-sequences', [ContactWebController::class, 'emailSequences'])->name('crm.email-sequences.index');
    Route::get('/crm/call-logs', [ContactWebController::class, 'callLogs'])->name('crm.call-logs.index');

    // Chantier 8.2 — real backend + real Vue page existed for these, just missing the web route.
    Route::get('/crm/quotes', [QuoteWebController::class, 'index'])->name('crm.quotes-page.index');
    Route::get('/crm/quotes/{quote}', [QuoteWebController::class, 'show'])->name('crm.quotes-page.show');
    Route::get('/crm/territories', [TerritoryWebController::class, 'index'])->name('crm.territories-page.index');
    Route::get('/crm/forecast', [ForecastWebController::class, 'index'])->name('crm.forecast-page.index');
    Route::get('/crm/opportunities/scoring', [OpportunityScoringWebController::class, 'index'])->name('crm.opportunities.scoring-page.index');

    // Chantier 10: both are real, self-fetching pages (call the real, already-routed
    // /api/v1/crm/pipelines and /api/v1/crm/opportunities endpoints) that had no web route
    // at all — thin Inertia::render() closures, same precedent as Inventory/Logistics'
    // self-fetch pages (e.g. stock/movements).
    Route::get('/crm/opportunities/kanban', fn () => Inertia::render('CRM/Opportunities/Kanban'))
        ->name('crm.opportunities.kanban');
    Route::get('/crm/opportunities/create', fn () => Inertia::render('CRM/Opportunities/CreateForm'))
        ->name('crm.opportunities.create');

    // Chantier 10: Dashboard/Index.vue is real (self-fetches /api/v1/crm/{contacts,leads,
    // opportunities,activities}) but had no web route; it's also the target the AI dashboard's
    // "Ma performance" insight link expects (AiDashboardService's action_route
    // 'CRM/Dashboard/Index' → InsightCard.vue's navigate() strips the module+trailing segment
    // to build '/crm/dashboard'). The sibling flat Dashboard.vue (deleted) needed server props
    // ('stats', chart data) that no controller ever supplied — unroutable as-is and fully
    // superseded by the self-sufficient Dashboard/Index.vue.
    Route::get('/crm/dashboard', fn () => Inertia::render('CRM/Dashboard/Index'))
        ->name('crm.dashboard');
});
