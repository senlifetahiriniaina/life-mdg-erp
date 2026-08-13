<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Helpdesk\Http\Controllers\Web\ChatWebController;
use Modules\Helpdesk\Http\Controllers\Web\EscalationWebController;
use Modules\Helpdesk\Http\Controllers\Web\PortalWebController;
use Modules\Helpdesk\Http\Controllers\Web\TicketWebController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'module:Helpdesk'])->group(function () {
    Route::get('/helpdesk/tickets', [TicketWebController::class, 'index'])->name('helpdesk.tickets.index');
    Route::get('/helpdesk/tickets/{ticket}', [TicketWebController::class, 'show'])->name('helpdesk.tickets.show');

    // Live Chat agent dashboard
    Route::get('/helpdesk/chat', [ChatWebController::class, 'index'])->name('helpdesk.chat.index');

    // Self-service portal
    Route::get('/helpdesk/portal', [PortalWebController::class, 'index'])->name('helpdesk.portal.index');

    // SLA & Escalation management
    Route::get('/helpdesk/escalation', [EscalationWebController::class, 'index'])->name('helpdesk.escalation.index');

    // Knowledge base
    Route::get('/helpdesk/knowledge-base', fn () => Inertia::render('Helpdesk/KnowledgeBase/Index'))->name('helpdesk.knowledge-base.index');

    // Community forum
    Route::get('/helpdesk/forum', fn () => Inertia::render('Helpdesk/Forum/Index'))->name('helpdesk.forum.index');
    Route::get('/helpdesk/forum/{post}', fn ($post) => Inertia::render('Helpdesk/Forum/Show', ['postId' => $post]))->name('helpdesk.forum.show');

    // CSAT surveys
    Route::get('/helpdesk/csat', fn () => Inertia::render('Helpdesk/CSAT/Index'))->name('helpdesk.csat.index');
});
