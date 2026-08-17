<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Helpdesk\Http\Controllers\Web\ChatWebController;
use Modules\Helpdesk\Http\Controllers\Web\EscalationWebController;
use Modules\Helpdesk\Http\Controllers\Web\PortalWebController;
use Modules\Helpdesk\Http\Controllers\Web\SlaAutomationWebController;
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

    // SLA Automation — breach detection/compliance/performance dashboard for
    // SlaController's real, migrated, and already-live SlaPolicy/SlaBreach
    // engine (SlaService::apply() runs on every ticket creation). Genuinely
    // distinct from the escalation-rule config above, which manages the
    // separate HelpdeskSlaPolicy/EscalationRule pair instead.
    Route::get('/helpdesk/sla-automation', [SlaAutomationWebController::class, 'index'])->name('helpdesk.sla-automation.index');

    // Knowledge base
    Route::get('/helpdesk/knowledge-base', fn () => Inertia::render('Helpdesk/KnowledgeBase/Index'))->name('helpdesk.knowledge-base.index');

    // Community forum
    Route::get('/helpdesk/forum', fn () => Inertia::render('Helpdesk/Forum/Index'))->name('helpdesk.forum.index');
    Route::get('/helpdesk/forum/{post}', fn ($post) => Inertia::render('Helpdesk/Forum/Show', ['id' => $post]))->name('helpdesk.forum.show');

    // CSAT surveys
    Route::get('/helpdesk/csat', fn () => Inertia::render('Helpdesk/CSAT/Index'))->name('helpdesk.csat.index');

    // AI response-template management (not ticket-scoped — response-suggestions is
    // ticket-scoped and tested from this page against a chosen ticket)
    Route::get('/helpdesk/ai-bot', fn () => Inertia::render('Helpdesk/AIBot/Index'))->name('helpdesk.ai-bot.index');

    // Manager view: agent metrics/trends + team benchmarking (cs-ai). Coaching
    // recommendations / SMART goals / skill-proficiency scoring are intentionally
    // NOT wired here — CLAUDE.md's "Known gaps" documents agent talent management
    // as excluded from Life MDG's scope (360°-review territory).
    Route::get('/helpdesk/quality-assurance', fn () => Inertia::render('Helpdesk/QualityAssurance/Index'))->name('helpdesk.quality-assurance.index');
});

// Self-service answer-bot widget — standalone (no AppLayout, by design, so it can
// be embedded via iframe on an external site), calling the already-public
// AnswerBotController::ask()/deflect() endpoints (helpdesk/bot/ask, .../deflect —
// no auth:sanctum). Deliberately outside the ['auth', 'module:Helpdesk'] group
// above: an anonymous visitor asking a question is exactly who this is for.
Route::get('/helpdesk/bot/widget', fn () => Inertia::render('Helpdesk/Bot/Widget'))->name('helpdesk.bot.widget');
