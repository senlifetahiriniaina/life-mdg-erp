<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Web\ContactWebController;

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
});
