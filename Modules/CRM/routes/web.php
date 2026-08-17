<?php

use Illuminate\Support\Facades\Route;
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
    Route::get('/crm/email-sequences', [ContactWebController::class, 'emailSequences'])->name('crm.email-sequences.index');
    Route::get('/crm/call-logs', [ContactWebController::class, 'callLogs'])->name('crm.call-logs.index');

    // Chantier 8.2 — real backend + real Vue page existed for these, just missing the web route.
    Route::get('/crm/quotes', [QuoteWebController::class, 'index'])->name('crm.quotes-page.index');
    Route::get('/crm/quotes/{quote}', [QuoteWebController::class, 'show'])->name('crm.quotes-page.show');
    Route::get('/crm/territories', [TerritoryWebController::class, 'index'])->name('crm.territories-page.index');
    Route::get('/crm/forecast', [ForecastWebController::class, 'index'])->name('crm.forecast-page.index');
    Route::get('/crm/opportunities/scoring', [OpportunityScoringWebController::class, 'index'])->name('crm.opportunities.scoring-page.index');
});
