<?php

use Illuminate\Support\Facades\Route;
use Modules\Helpdesk\Http\Controllers\Api\AnswerBotController;
use Modules\Helpdesk\Http\Controllers\Api\CsatController;
use Modules\Helpdesk\Http\Controllers\Api\EscalationController;
use Modules\Helpdesk\Http\Controllers\Api\ForumController;
use Modules\Helpdesk\Http\Controllers\Api\HelpdeskAIController;
use Modules\Helpdesk\Http\Controllers\Api\KbArticleController;
use Modules\Helpdesk\Http\Controllers\Api\KbCategoryController;
use Modules\Helpdesk\Http\Controllers\Api\KbChatbotController;
use Modules\Helpdesk\Http\Controllers\Api\KbPortalController;
use Modules\Helpdesk\Http\Controllers\Api\KnowledgeBaseController;
use Modules\Helpdesk\Http\Controllers\Api\LiveChatController;
use Modules\Helpdesk\Http\Controllers\Api\SlaController;
use Modules\Helpdesk\Http\Controllers\Api\TeamController;
use Modules\Helpdesk\Http\Controllers\Api\CommunityForumController;
use Modules\Helpdesk\Http\Controllers\Api\CustomerServiceAIController;
use Modules\Helpdesk\Http\Controllers\Api\TicketCommentController;
use Modules\Helpdesk\Http\Controllers\Api\TicketController;

// Public routes (no auth required)
Route::prefix('v1')->group(function () {
    Route::post('helpdesk/chat/sessions', [LiveChatController::class, 'startSession']);
    Route::post('helpdesk/chat/sessions/{session}/messages', [LiveChatController::class, 'sendMessage']);
    // Public KB Portal (no auth to browse published articles)
    Route::get('helpdesk/kb/portal/articles', [KbPortalController::class, 'index']);
    Route::get('helpdesk/kb/portal/articles/{kbPortalArticle}', [KbPortalController::class, 'show']);
    // Answer Bot — public self-service widget (stricter rate limit for unauthenticated callers)
    Route::post('helpdesk/bot/ask', [AnswerBotController::class, 'ask'])->middleware('throttle:20,1');
    Route::post('helpdesk/bot/deflect', [AnswerBotController::class, 'deflect'])->middleware('throttle:20,1');
});

// Self-service routes: any authenticated user (community forum, live chat agent actions)
Route::middleware(['auth:sanctum', 'session.security', 'module:Helpdesk', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Live Chat (agent actions — any authenticated user can act as agent)
    Route::post('helpdesk/chat/sessions/{session}/assign', [LiveChatController::class, 'assignAgent']);
    Route::post('helpdesk/chat/sessions/{session}/convert-to-ticket', [LiveChatController::class, 'convertToTicket']);

    // Community Forum (open to all authenticated users)
    Route::get('helpdesk/forum/posts', [ForumController::class, 'index'])->name('helpdesk.forum.index');
    Route::get('helpdesk/forum/posts/{forumPost}', [ForumController::class, 'show'])->name('helpdesk.forum.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/forum/posts', [ForumController::class, 'store'])->name('helpdesk.forum.store');
        Route::put('helpdesk/forum/posts/{forumPost}', [ForumController::class, 'update'])->name('helpdesk.forum.update');
        Route::delete('helpdesk/forum/posts/{forumPost}', [ForumController::class, 'destroy'])->name('helpdesk.forum.destroy');
        Route::post('helpdesk/forum/posts/{forumPost}/replies', [ForumController::class, 'storeReply'])->name('helpdesk.forum.replies.store');
        Route::delete('helpdesk/forum/posts/{forumPost}/replies/{forumReply}', [ForumController::class, 'destroyReply'])->name('helpdesk.forum.replies.destroy');
        Route::post('helpdesk/forum/posts/{forumPost}/accept-answer/{forumReply}', [ForumController::class, 'acceptAnswer'])->name('helpdesk.forum.accept-answer');
        Route::post('helpdesk/forum/posts/{forumPost}/vote', [ForumController::class, 'votePost'])->name('helpdesk.forum.vote-post');
        Route::post('helpdesk/forum/posts/{forumPost}/replies/{forumReply}/vote', [ForumController::class, 'voteReply'])->name('helpdesk.forum.vote-reply');
    });
});

// Default: Simple GET throttle (1000 req/min)
Route::middleware(['auth:sanctum', 'session.security', 'module:Helpdesk', 'throttle:simple_get'])->prefix('v1')->group(function () {

    // Tickets
    Route::get('helpdesk/tickets', [TicketController::class, 'index'])->name('helpdesk.tickets.index');
    Route::get('helpdesk/tickets/{ticket}', [TicketController::class, 'show'])->name('helpdesk.tickets.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/tickets', [TicketController::class, 'store'])->name('helpdesk.tickets.store');
        Route::put('helpdesk/tickets/{ticket}', [TicketController::class, 'update'])->name('helpdesk.tickets.update');
        Route::delete('helpdesk/tickets/{ticket}', [TicketController::class, 'destroy'])->name('helpdesk.tickets.destroy');
        Route::post('helpdesk/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('helpdesk.tickets.assign');
        Route::post('helpdesk/tickets/{ticket}/resolve', [TicketController::class, 'resolve'])->name('helpdesk.tickets.resolve');
        Route::post('helpdesk/tickets/{ticket}/close', [TicketController::class, 'close'])->name('helpdesk.tickets.close');
        Route::post('helpdesk/tickets/{ticket}/escalate', [TicketController::class, 'escalate'])->name('helpdesk.tickets.escalate');
    });

    // Ticket Comments
    Route::get('helpdesk/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('helpdesk.tickets.comments.index');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('helpdesk.tickets.comments.store');
        Route::put('helpdesk/tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'update'])->name('helpdesk.tickets.comments.update');
        Route::delete('helpdesk/tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy'])->name('helpdesk.tickets.comments.destroy');
    });

    // Teams
    Route::get('helpdesk/teams', [TeamController::class, 'index'])->name('helpdesk.teams.index');
    Route::get('helpdesk/teams/{team}', [TeamController::class, 'show'])->name('helpdesk.teams.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/teams', [TeamController::class, 'store'])->name('helpdesk.teams.store');
        Route::put('helpdesk/teams/{team}', [TeamController::class, 'update'])->name('helpdesk.teams.update');
        Route::delete('helpdesk/teams/{team}', [TeamController::class, 'destroy'])->name('helpdesk.teams.destroy');
    });

    // Knowledge Base — static routes before apiResource to avoid {kbArticle} shadowing
    Route::get('helpdesk/kb/articles/suggest', [KbArticleController::class, 'suggest'])->name('helpdesk.kb.articles.suggest');
    Route::get('helpdesk/kb/articles/suggestions', [KnowledgeBaseController::class, 'suggestions']);
    Route::get('helpdesk/kb/articles/popular', [KnowledgeBaseController::class, 'popular']);
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('helpdesk/kb/articles/search', [KnowledgeBaseController::class, 'search']);
    });
    Route::get('helpdesk/kb/categories', [KbCategoryController::class, 'index'])->name('helpdesk.kb.categories.index');
    Route::get('helpdesk/kb/categories/{kbCategory}', [KbCategoryController::class, 'show'])->name('helpdesk.kb.categories.show');
    Route::get('helpdesk/kb/articles', [KbArticleController::class, 'index'])->name('helpdesk.kb.articles.index');
    Route::get('helpdesk/kb/articles/{kbArticle}', [KbArticleController::class, 'show'])->name('helpdesk.kb.articles.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/kb/categories', [KbCategoryController::class, 'store'])->name('helpdesk.kb.categories.store');
        Route::put('helpdesk/kb/categories/{kbCategory}', [KbCategoryController::class, 'update'])->name('helpdesk.kb.categories.update');
        Route::delete('helpdesk/kb/categories/{kbCategory}', [KbCategoryController::class, 'destroy'])->name('helpdesk.kb.categories.destroy');
        Route::post('helpdesk/kb/articles', [KbArticleController::class, 'store'])->name('helpdesk.kb.articles.store');
        Route::put('helpdesk/kb/articles/{kbArticle}', [KbArticleController::class, 'update'])->name('helpdesk.kb.articles.update');
        Route::delete('helpdesk/kb/articles/{kbArticle}', [KbArticleController::class, 'destroy'])->name('helpdesk.kb.articles.destroy');
        Route::post('helpdesk/kb/articles/{kbArticle}/feedback', [KbArticleController::class, 'feedback'])->name('helpdesk.kb.articles.feedback');
    });

    Route::middleware('throttle:ai')->prefix('helpdesk/ai')->group(function () {
        Route::post('categorize', [HelpdeskAIController::class, 'categorize']);
        Route::post('suggest-response', [HelpdeskAIController::class, 'suggestResponse']);
        Route::post('summarize', [HelpdeskAIController::class, 'summarize']);
        Route::post('predict-escalation', [HelpdeskAIController::class, 'predictEscalation']);
        Route::post('kb-chatbot', [KbChatbotController::class, 'answer']);
        Route::post('answer-from-kb', [KbChatbotController::class, 'answer']);
    });

    // SLA Policies
    Route::get('helpdesk/sla-policies', [EscalationController::class, 'indexSla']);
    Route::get('helpdesk/sla-policies/{slaPolicy}', [EscalationController::class, 'showSla']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/sla-policies', [EscalationController::class, 'storeSla']);
        Route::put('helpdesk/sla-policies/{slaPolicy}', [EscalationController::class, 'updateSla']);
        Route::delete('helpdesk/sla-policies/{slaPolicy}', [EscalationController::class, 'destroySla']);
    });

    // Escalation Rules
    Route::get('helpdesk/escalation-rules', [EscalationController::class, 'indexRules']);
    Route::get('helpdesk/escalation-rules/{escalationRule}', [EscalationController::class, 'showRule']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/escalation-rules', [EscalationController::class, 'storeRule']);
        Route::put('helpdesk/escalation-rules/{escalationRule}', [EscalationController::class, 'updateRule']);
        Route::delete('helpdesk/escalation-rules/{escalationRule}', [EscalationController::class, 'destroyRule']);
    });

    // KB Portal (authenticated actions)
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/kb/portal/articles', [KbPortalController::class, 'store']);
        Route::delete('helpdesk/kb/portal/articles/{kbPortalArticle}', [KbPortalController::class, 'destroy']);
    });

    // SLA Automation
    Route::get('helpdesk/sla/policies', [SlaController::class, 'indexPolicies']);
    Route::get('helpdesk/sla/policies/{policy}', [SlaController::class, 'showPolicy']);
    Route::get('helpdesk/sla/breaches/pending', [SlaController::class, 'pendingBreaches']);
    Route::get('helpdesk/sla/breaches/ticket/{ticketId}', [SlaController::class, 'ticketBreaches']);
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('helpdesk/sla/stats/compliance', [SlaController::class, 'complianceStats']);
        Route::get('helpdesk/sla/stats/performance', [SlaController::class, 'performanceReport']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/sla/policies', [SlaController::class, 'storePolicies']);
        Route::put('helpdesk/sla/policies/{policy}', [SlaController::class, 'updatePolicy']);
        Route::delete('helpdesk/sla/policies/{policy}', [SlaController::class, 'deletePolicy']);
        Route::post('helpdesk/sla/check', [SlaController::class, 'checkBreaches']);
        Route::post('helpdesk/sla/escalate', [SlaController::class, 'runEscalations']);
    });

    // CSAT Reports & Campaigns
    Route::get('helpdesk/csat/surveys', [CsatController::class, 'indexSurveys'])->name('helpdesk.csat.surveys.index');
    Route::get('helpdesk/csat/campaigns', [CsatController::class, 'indexCampaigns'])->name('helpdesk.csat.campaigns.index');
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('helpdesk/csat/report', [CsatController::class, 'report'])->name('helpdesk.csat.report');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/csat/surveys', [CsatController::class, 'storeSurvey'])->name('helpdesk.csat.surveys.store');
        Route::put('helpdesk/csat/surveys/{survey}', [CsatController::class, 'updateSurvey'])->name('helpdesk.csat.surveys.update');
        Route::post('helpdesk/csat/campaigns', [CsatController::class, 'storeCampaign'])->name('helpdesk.csat.campaigns.store');
    });


    // Knowledge Base (new unified controller) — static routes before parameterised ones
    Route::get('helpdesk/kb/stats', [KnowledgeBaseController::class, 'stats']);
    Route::get('helpdesk/kb/categories', [KnowledgeBaseController::class, 'indexCategories']);
    Route::get('helpdesk/kb/categories/{category}', [KnowledgeBaseController::class, 'showCategory']);
    Route::get('helpdesk/kb/articles', [KnowledgeBaseController::class, 'indexArticles']);
    Route::get('helpdesk/kb/articles/{article}', [KnowledgeBaseController::class, 'showArticle']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/kb/categories', [KnowledgeBaseController::class, 'storeCategory']);
        Route::put('helpdesk/kb/categories/{category}', [KnowledgeBaseController::class, 'updateCategory']);
        Route::delete('helpdesk/kb/categories/{category}', [KnowledgeBaseController::class, 'destroyCategory']);
        Route::post('helpdesk/kb/articles', [KnowledgeBaseController::class, 'storeArticle']);
        Route::put('helpdesk/kb/articles/{article}', [KnowledgeBaseController::class, 'updateArticle']);
        Route::delete('helpdesk/kb/articles/{article}', [KnowledgeBaseController::class, 'destroyArticle']);
        Route::post('helpdesk/kb/articles/{article}/publish', [KnowledgeBaseController::class, 'publishArticle']);
        Route::post('helpdesk/kb/articles/{article}/view', [KnowledgeBaseController::class, 'recordView']);
        Route::post('helpdesk/kb/articles/{article}/feedback', [KnowledgeBaseController::class, 'submitFeedback']);
    });
});

// ── Customer Service AI (sentiment, escalation, response AI, agent performance) ──
// Reconnects Modules\Helpdesk\Http\Controllers\Api\CustomerServiceAIController's
// 21 existing action methods — no new controller logic added here.
Route::middleware(['auth:sanctum', 'session.security', 'module:Helpdesk', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Sentiment / emotion / language analysis
    Route::get('helpdesk/cs-ai/sentiment', [CustomerServiceAIController::class, 'getSentimentAnalysis']);
    Route::get('helpdesk/cs-ai/emotion', [CustomerServiceAIController::class, 'getEmotionAnalysis']);
    Route::get('helpdesk/cs-ai/language', [CustomerServiceAIController::class, 'getLanguageDetection']);

    // Routing rules
    Route::get('helpdesk/cs-ai/routing-rules', [CustomerServiceAIController::class, 'listRoutingRules']);

    // Escalation prediction
    Route::get('helpdesk/cs-ai/escalation-prediction', [CustomerServiceAIController::class, 'getEscalationPrediction']);
    Route::get('helpdesk/cs-ai/urgency-factors', [CustomerServiceAIController::class, 'getUrgencyFactors']);

    // AI response suggestions
    Route::get('helpdesk/cs-ai/response-templates', [CustomerServiceAIController::class, 'listResponseTemplates']);
    Route::get('helpdesk/cs-ai/response-suggestions', [CustomerServiceAIController::class, 'getResponseSuggestions']);

    // Satisfaction / NPS prediction
    Route::get('helpdesk/cs-ai/satisfaction-prediction', [CustomerServiceAIController::class, 'getSatisfactionPrediction']);
    Route::get('helpdesk/cs-ai/nps-prediction', [CustomerServiceAIController::class, 'getNPSPrediction']);

    // Agent performance analytics
    Route::get('helpdesk/cs-ai/agents/metrics', [CustomerServiceAIController::class, 'getAgentMetrics']);
    Route::get('helpdesk/cs-ai/agents/trends', [CustomerServiceAIController::class, 'getAgentPerformanceTrends']);
    Route::get('helpdesk/cs-ai/agents/skills', [CustomerServiceAIController::class, 'getAgentSkills']);
    Route::get('helpdesk/cs-ai/coaching-recommendations', [CustomerServiceAIController::class, 'listCoachingRecommendations']);
    Route::get('helpdesk/cs-ai/team-benchmarking', [CustomerServiceAIController::class, 'getTeamBenchmarking']);
    Route::get('helpdesk/cs-ai/performance-goals', [CustomerServiceAIController::class, 'listPerformanceGoals']);

    Route::middleware('throttle:create_post')->group(function () {
        Route::post('helpdesk/cs-ai/routing-rules', [CustomerServiceAIController::class, 'createRoutingRule']);
        Route::post('helpdesk/cs-ai/response-suggestions/feedback', [CustomerServiceAIController::class, 'recordSuggestionFeedback']);
        Route::post('helpdesk/cs-ai/coaching-recommendations', [CustomerServiceAIController::class, 'createCoachingRecommendation']);
        Route::post('helpdesk/cs-ai/performance-goals', [CustomerServiceAIController::class, 'createPerformanceGoal']);
        Route::post('helpdesk/cs-ai/performance-goals/progress', [CustomerServiceAIController::class, 'updateGoalProgress']);
    });
});

// ── Community Forums ──────────────────────────────────────────────────────
// Public: search and popular threads (no auth needed)
Route::prefix('v1/helpdesk')->group(function () {
    Route::get('forums/search', [CommunityForumController::class, 'search']);
    Route::get('forums/popular', [CommunityForumController::class, 'popular']);
    Route::get('forums', [CommunityForumController::class, 'indexForums']);
    Route::get('forums/{forum}', [CommunityForumController::class, 'showForum']);
    Route::get('forums/{forum}/threads', [CommunityForumController::class, 'indexThreads']);
    Route::get('threads/{thread}', [CommunityForumController::class, 'showThread']);
});

// Authenticated forum actions
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/helpdesk')->group(function () {
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('forums', [CommunityForumController::class, 'storeForumBoard']);
        Route::post('forums/{forum}/threads', [CommunityForumController::class, 'storeThread']);
        Route::post('threads/{thread}/replies', [CommunityForumController::class, 'storeReply']);
        Route::post('threads/{thread}/close', [CommunityForumController::class, 'closeThread']);
        Route::post('threads/{thread}/vote', [CommunityForumController::class, 'voteThread']);
        Route::post('threads/{thread}/replies/{reply}/accept-answer', [CommunityForumController::class, 'acceptAnswer']);
        Route::post('replies/{reply}/vote', [CommunityForumController::class, 'voteReply']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/helpdesk')->group(function () {
    Route::post('ai/assist', [\Modules\Helpdesk\Http\Controllers\Api\HelpdeskAiAssistController::class, 'assist'])
        ->name('helpdesk.ai.assist');
});
