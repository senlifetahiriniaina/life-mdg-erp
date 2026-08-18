<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;
use Modules\Helpdesk\Services\SentimentAnalysisService;
use Modules\Helpdesk\Services\PredictiveEscalationService;
use Modules\Helpdesk\Services\AiResponseService;
use Modules\Helpdesk\Services\SatisfactionPredictionService;

uses(RefreshDatabase::class);

// NOTE: This block was originally written against a phantom `SentimentAnalysisService::analyze()`
// contract that was never built (undefined method — see git history / SentimentAnalysisTest.php,
// which targeted a matching but equally phantom `POST /api/v1/helpdesk/sentiment/analyze` HTTP
// endpoint and has been removed for the same reason). The REAL, live sentiment analysis surface
// is `Modules\Helpdesk\Http\Controllers\Api\CustomerServiceAIController::getSentimentAnalysis()`,
// which reads precomputed `cs_sentiment_scores` rows — it does not call this service at all.
// This service IS real production code though: `AiResponseService`, `SatisfactionPredictionService`,
// `AgentPerformanceAnalyticsService`, and `PredictiveEscalationService` all call
// `analyzeTicketSentiment()` on it. These tests now exercise its real, existing public methods.
describe('SentimentAnalysisService', function () {
    test('analyzes positive sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('good great excellent amazing');

        expect($result['sentiment'])->toBe('positive');
        expect($result['confidence'])->toBe(1.0);
        expect($result['score'])->toBe(100);
    });

    test('analyzes negative sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('bad terrible awful horrible');

        expect($result['sentiment'])->toBe('negative');
        expect($result['confidence'])->toBe(1.0);
        expect($result['score'])->toBe(0);
    });

    test('analyzes neutral sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('The product arrived on time.');

        expect($result['sentiment'])->toBe('neutral');
        expect($result['score'])->toBe(50);
    });

    test('detects emotions in text', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->detectEmotions('I am very angry and furious!');

        expect($result)->toBeArray();
        expect(count($result))->toBeGreaterThan(0);
    });

    test('detects anger emotion specifically', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->detectEmotions('I am furious!');

        expect($result)->toHaveKey('anger');
        expect($result['anger'])->toBeGreaterThan(0);
    });

    test('provides emotion confidence scores', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->detectEmotions('I am angry and frustrated!');

        expect($result)->toBeArray();
        expect($result)->toHaveKey('anger');
        expect($result)->toHaveKey('frustration');
        foreach ($result as $score) {
            expect($score)->toBeGreaterThan(0)->toBeLessThanOrEqual(1);
        }
    });

    test('returns multiple emotions for complex text', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->detectEmotions('I am frustrated and confused but also satisfied with some aspects.');

        expect(count($result))->toBeGreaterThan(1);
    });

    test('analyzes multi-language text with an explicit language tag', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('Bonjour et merci beaucoup!', 'fr');

        expect($result['original_language'])->toBe('fr');
        expect($result['sentiment'])->toBeString();
    });

    test('auto-detects language from known keywords', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('Bonjour, merci pour votre aide');

        // Detection is a small hardcoded fr/es/de keyword list (see detectLanguage()) — it is not
        // general-purpose language ID, so we assert against its real, narrow behavior.
        expect($result['original_language'])->toBe('fr');
    });

    test('defaults to english when no known-language keywords are present', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('Great service today');

        expect($result['original_language'])->toBe('en');
    });

    test('returns sentiment score between 0 and 100', function () {
        $service = app(SentimentAnalysisService::class);

        expect($service->calculateSentimentScore('This is okay.'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });

    test('handles empty text gracefully by returning a neutral score', function () {
        $service = app(SentimentAnalysisService::class);

        expect($service->calculateSentimentScore(''))->toBe(50);
    });

    test('handles text with no lexicon matches without dividing by zero', function () {
        // Regression guard: calculateSentimentScore() used to throw DivisionByZeroError for any
        // text containing words but zero sentiment-lexicon matches, because $positiveScore/
        // $negativeScore get silently promoted from int 0 to float 0.0 by the += float accumulator,
        // and the old `$total === 0` strict comparison never matched a float zero.
        $service = app(SentimentAnalysisService::class);

        expect($service->calculateSentimentScore('The product arrived on time.'))->toBe(50);
    });

    test('handles very long text', function () {
        $service = app(SentimentAnalysisService::class);
        $longText = str_repeat('This is a good test. ', 500);

        expect($service->calculateSentimentScore($longText))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });

    test('handles special characters and emojis', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeMultiLanguageSentiment('Great! Amazing! 👍 🎉');

        expect($result['sentiment'])->toBe('positive');
    });

    test('real-time sentiment analysis flags escalation for angry messages', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyzeRealtimeSentiment('I am extremely angry! This is unacceptable!');

        expect($result['sentiment'])->toBe('negative');
        expect($result['should_escalate'])->toBeTrue();
    });

    // NOTE: routeTicketBySentiment() is intentionally not covered here — it calls
    // AuditLog::create() without a `tenant_id`, and `audit_logs.tenant_id` is NOT NULL,
    // so it currently fails for every caller. Pre-existing, out of scope for this cluster
    // (the same missing-tenant_id gap exists in ~8 other AuditLog::create() call sites
    // across AiResponseService/PredictiveEscalationService/AgentPerformanceAnalyticsService/
    // SatisfactionPredictionService) — flagged for a separate, dedicated fix.

    test('batch analyzes sentiment for multiple tickets', function () {
        $service = app(SentimentAnalysisService::class);
        $tickets = Ticket::factory()->count(3)->create(['description' => 'good great excellent amazing']);

        $results = $service->batchAnalyzeSentiment($tickets->pluck('id')->toArray());

        expect($results)->toHaveCount(3);
        foreach ($tickets as $ticket) {
            expect($results[$ticket->id]['sentiment'])->toBe('positive');
        }
    });
});

// NOTE: This block was originally written against a phantom `PredictiveEscalationService::predict()`
// contract that was never built (undefined method — see git history / PredictiveEscalationTest.php,
// which targeted a matching but equally phantom `POST /api/v1/helpdesk/escalation/predict` HTTP
// endpoint — plus `/escalation/batch-predict` and `/escalation/accuracy-metrics`, 38 tests total —
// and has been removed for the same reason: zero real route or migration ever backed it). The REAL,
// live escalation-prediction surface is `POST /api/v1/helpdesk/ai/predict-escalation` →
// `HelpdeskAIController::predictEscalation()` → `HelpdeskAIService::predictEscalation()` → Core
// `AIService` (LLM-backed, graceful fallback) — an entirely different, already-routed component; see
// the new `Modules/Helpdesk/tests/Feature/HelpdeskAiEscalationResponseTest.php` for its coverage.
// `PredictiveEscalationService` below IS separately real, substantial, on-the-fly heuristic-scoring
// production code (used nowhere else, same profile as SatisfactionPredictionService) — this block now
// exercises its actual public methods: `predictEscalationNeed()`, `predictSlaBreach()`,
// `recommendEscalationActions()`, `trackPredictionAccuracy()`.
//
// Two real column-shape corrections along the way: (1) `hd_tickets` has no `metadata` or
// `escalation_level` column (see the docblock on migration
// 2026_06_19_000021_add_source_polymorphic_to_hd_tickets.php — customer_id/contact_id/metadata-style
// columns were deliberately never wired to relations, superseded by source_type/source_id), so the
// old "recently escalated" test's `metadata => ['escalated_at' => ...]` fixture was inert; (2)
// `predictEscalationNeed()`'s `predicted_escalation_level` values are `L1`/`L2`/`L3`/`MANAGEMENT`
// (see `ESCALATION_LEVELS` const), not the old test's lowercase `level1`/`management` guesses. A real,
// latent bug was also found and fixed while doing this: `calculateIssueComplexity()` called the
// undefined `Ticket::messages()` relation (only `comments()` exists) — same bug class as the
// `SatisfactionPredictionService` fix in the sibling block above, fixed at
// `PredictiveEscalationService.php` in `calculateIssueComplexity()`.
describe('PredictiveEscalationService', function () {
    test('predicts escalation need', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $result = $service->predictEscalationNeed($ticket);

        // toBeBool(): the original `toBeBoolean()` call here (and at `will_breach` below) was
        // itself phantom (not a real Pest expectation) — masked until now by the `predict()`
        // undefined-method error firing first on every run.
        expect($result['should_escalate'])->toBeBool();
        expect($result['urgency_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('escalation score increases with SLA time elapsed', function () {
        // Real "age" signal is `calculateWaitTimeScore()`: hours-elapsed-vs-SLA-window, not raw
        // calendar age — with no `sla_due_at` set (the common case; no default SlaPolicy is seeded
        // in this test suite) every ticket gets the same flat 0.3 default regardless of age, so the
        // comparison needs an explicit SLA window to be meaningful.
        $service = app(PredictiveEscalationService::class);
        $newTicket = Ticket::factory()->create([
            'created_at' => now()->subHours(1),
            'sla_due_at' => now()->addHours(23), // 24h window, ~4% elapsed
            'description' => 'Standard ticket contents.',
        ]);
        $oldTicket = Ticket::factory()->create([
            'created_at' => now()->subHours(20),
            'sla_due_at' => now()->addHours(4), // 24h window, ~83% elapsed
            'description' => 'Standard ticket contents.',
        ]);

        $newResult = $service->predictEscalationNeed($newTicket);
        $oldResult = $service->predictEscalationNeed($oldTicket);

        expect($oldResult['urgency_score'])->toBeGreaterThan($newResult['urgency_score']);
    });

    test('escalation score increases with priority', function () {
        // Real priority effect lives only in `calculateIssueComplexity()`, and only bumps for
        // `critical`/`high` (not `urgent` — a real, narrow behavior, not a phantom gap) so the
        // comparison must use `critical` vs `low` to be deterministic.
        $service = app(PredictiveEscalationService::class);
        $lowPriority = Ticket::factory()->create([
            'priority' => 'low',
            'description' => 'Standard ticket contents.',
        ]);
        $criticalPriority = Ticket::factory()->create([
            'priority' => 'critical',
            'description' => 'Standard ticket contents.',
        ]);

        $lowResult = $service->predictEscalationNeed($lowPriority);
        $criticalResult = $service->predictEscalationNeed($criticalPriority);

        expect($criticalResult['urgency_score'])->toBeGreaterThan($lowResult['urgency_score']);
    });

    test('predicts sla breach', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addMinutes(15),
            'status' => 'open',
        ]);

        $result = $service->predictSlaBreach($ticket);

        expect($result['will_breach'])->toBeBool();
    });

    test('identifies escalation level', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        $result = $service->predictEscalationNeed($ticket);

        expect($result['predicted_escalation_level'])->toBeIn(['L1', 'L2', 'L3', 'MANAGEMENT']);
    });

    test('provides escalation reasons', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'created_at' => now()->subDays(2),
        ]);

        $result = $service->recommendEscalationActions($ticket);

        expect($result)->toBeArray();
    });

    test('provides confidence score', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predictEscalationNeed($ticket);

        expect($result['confidence'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('tracks prediction accuracy across multiple tickets', function () {
        // NOTE: retargeted from "handles recently escalated tickets", which relied on a phantom
        // `metadata => ['escalated_at' => ...]` ticket attribute (no such column — see block NOTE
        // above) and a phantom `already_escalated` result key that predictEscalationNeed() never
        // returns. `trackPredictionAccuracy()` is real, un-covered-elsewhere production code that
        // exercises the same "does this service behave sanely across a batch of tickets" intent.
        $service = app(PredictiveEscalationService::class);
        $tickets = Ticket::factory()->count(3)->create();

        $result = $service->trackPredictionAccuracy($tickets->pluck('id')->toArray());

        expect($result['total_predictions'])->toBe(3);
        expect($result['accuracy_rate'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        expect($result['predictions'])->toHaveCount(3);
    });
});

// NOTE: This block was originally written against a phantom `AiResponseService::suggest()`/
// `variants()` contract that was never built (undefined methods — see git history /
// AiResponseTest.php, which targeted a matching but equally phantom
// `POST /api/v1/helpdesk/ai-response/suggest` + `/ai-response/variants` HTTP contract, 37 tests
// total, and has been removed for the same reason: zero real route or migration ever backed it).
// The REAL, live AI-drafted-reply surface is `POST /api/v1/helpdesk/ai/suggest-response` →
// `HelpdeskAIController::suggestResponse()` → `HelpdeskAIService::suggestResponse()` → Core
// `AIService` (LLM-backed, graceful fallback) — an entirely different, already-routed component; see
// the new `Modules/Helpdesk/tests/Feature/HelpdeskAiEscalationResponseTest.php` for its coverage.
// `AiResponseService` below IS separately real, substantial, template/heuristic-based production
// code — this block now exercises its actual public methods: `generateResponseSuggestions()`
// (template + knowledge-base + sentiment-aware suggestions, already relevance-ranked),
// `generateVariationsOfTemplate()`, `personalizeResponse()`, `generateMultiLanguageResponses()`.
//
// `personalizeResponse()`'s `[CUSTOMER_NAME]`/`[COMPANY_NAME]` placeholder branches read
// `$ticket->customer`/`$ticket->company`, relations that were deliberately never defined on Ticket
// — see the docblock on migration 2026_06_19_000021_add_source_polymorphic_to_hd_tickets.php:
// "contact_id/customer_id/source_ref columns... had no matching relation on the Ticket model",
// intentionally superseded by the polymorphic source_type/source_id pattern used by
// `HelpdeskLinkable`. Those two branches are permanently-dead by design (degrade to a no-op, never
// throw) — not a bug, so the "personalizes response" test below exercises the placeholders that DO
// resolve instead. A real, latent bug was also found and fixed while doing this:
// `trackResponsePerformance()` called the undefined `Ticket::responses()` relation (only
// `comments()` exists) — same bug class as `PredictiveEscalationService`/`SatisfactionPredictionService`
// above, fixed at `AiResponseService.php`; not separately covered here since it wasn't part of this
// describe block's original intent.
describe('AiResponseService', function () {
    test('generates response suggestion', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create([
            'subject' => 'Cannot login',
            'description' => 'I cannot access my account',
        ]);

        $result = $service->generateResponseSuggestions($ticket);

        expect($result)->not->toBeEmpty();
        expect($result[0]['content'])->toBeString();
        expect(strlen($result[0]['content']))->toBeGreaterThan(10);
    });

    test('generates contextual response', function () {
        // Real contextual behavior is sentiment-driven (not topic-keyword-driven): a
        // negative-sentiment ticket pulls in `generateContextualSuggestions()`'s
        // `sentiment => 'negative'` entry alongside the fixed templates.
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create([
            'subject' => 'Terrible experience',
            'description' => 'This is unacceptable and awful, I am furious about this billing charge',
        ]);

        $result = $service->generateResponseSuggestions($ticket);

        expect(array_column($result, 'sentiment'))->toContain('negative');
    });

    test('personalizes response tone based on sentiment', function () {
        // NOTE: retargeted — the real service has no `tone` parameter (formal/friendly);
        // `personalizeResponse()`'s real, closest-equivalent behavior is prepending a
        // sentiment-driven greeting (negative/positive/neutral/mixed) ahead of the response body.
        $service = app(AiResponseService::class);
        $negativeTicket = Ticket::factory()->create(['description' => 'This is terrible and awful.']);
        $positiveTicket = Ticket::factory()->create(['description' => 'This is great and wonderful.']);

        $negativeResponse = $service->personalizeResponse('Base response.', $negativeTicket);
        $positiveResponse = $service->personalizeResponse('Base response.', $positiveTicket);

        expect($negativeResponse)->not->toBe($positiveResponse);
        expect($negativeResponse)->toContain('understand your concern');
        expect($positiveResponse)->toContain('positive feedback');
    });

    test('generates response variations from a template', function () {
        $service = app(AiResponseService::class);

        $result = $service->generateVariationsOfTemplate('{{greeting}}, we will help with your issue. {{closing}}', 3);

        expect($result)->toHaveCount(3);
    });

    test('variations are distinct', function () {
        $service = app(AiResponseService::class);

        $result = $service->generateVariationsOfTemplate('{{greeting}}, thanks for reaching out!', 3);

        expect(count(array_unique($result)))->toBe(3);
    });

    test('suggestions are ranked by relevance score', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->generateResponseSuggestions($ticket);
        $scores = array_column($result, 'relevance_score');

        expect($scores[0])->toBeGreaterThanOrEqual($scores[1] ?? 0);
    });

    test('personalizes response with ticket-specific placeholders', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();
        // 'category' is a real hd_tickets column but not in Ticket::$fillable — set directly.
        $ticket->category = 'billing';
        $ticket->save();

        $result = $service->personalizeResponse('Regarding [ISSUE_TYPE] ticket #[TICKET_ID].', $ticket);

        expect($result)->toContain('billing');
        expect($result)->toContain((string) $ticket->id);
    });

    test('relevance scores are bounded between 0 and 1', function () {
        // NOTE: retargeted from "provides confidence score #2" — AiResponseService has no
        // `confidence` concept anywhere (unlike PredictiveEscalationService); its real bounded
        // quality signal is `relevance_score` from `rankSuggestionsByRelevance()`.
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->generateResponseSuggestions($ticket);

        foreach ($result as $suggestion) {
            expect($suggestion['relevance_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
        }
    });

    test('generates multi-language responses', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->generateMultiLanguageResponses($ticket, ['en', 'es']);

        expect($result)->toHaveKeys(['en', 'es']);
        expect($result['en'][0]['language'])->toBe('en');
        expect($result['es'][0]['language'])->toBe('es');
    });
});

// NOTE: This block was originally written against a phantom `SatisfactionPredictionService::predict()`
// contract that was never built (undefined method — see git history / SatisfactionPredictionTest.php,
// which targeted a matching but equally phantom `POST /api/v1/helpdesk/satisfaction/predict` HTTP
// endpoint and has been removed for the same reason). The REAL, live satisfaction/NPS surface is
// `Modules\Helpdesk\Http\Controllers\Api\CustomerServiceAIController::getSatisfactionPrediction()` /
// `getNPSPrediction()`, which read precomputed `cs_satisfaction_predictions`/`cs_nps_predictors`
// rows — this service is a separate, on-the-fly predictor not wired to any route (and had zero real
// callers besides this test file, unlike SentimentAnalysisService above). It is kept as real,
// substantial existing code and exercised here via its actual public methods; two latent bugs found
// while doing so (`Ticket::messages()`/`Ticket::responses()` — undefined relations, only `comments()`
// exists) were fixed in the service since they permanently forced every prediction into the
// try/catch fallback path (`predicted_satisfaction = 50`, no factors/confidence/influencers).
//
// Also note the constructor: `SatisfactionPredictionService` extends `PersonalizationFramework` /
// `BaseService`, which rejects a company ID <= 0 — `app(SatisfactionPredictionService::class)` would
// resolve the default `companyId = 0` and throw `TenantException` before `predict*()` is ever
// reached, so these tests construct it explicitly with a valid company ID instead.
describe('SatisfactionPredictionService', function () {
    test('predicts satisfaction score on a 0-100 scale', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictTicketSatisfaction($ticket);

        expect($result)->not->toHaveKey('error');
        expect($result['predicted_satisfaction'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });

    test('provides confidence score between 0 and 1', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictTicketSatisfaction($ticket);

        expect($result['confidence'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('predicts higher satisfaction for fast resolution than for a slow open ticket', function () {
        $service = new SatisfactionPredictionService(1);

        $fastTicket = Ticket::factory()->create([
            'created_at' => now()->subMinutes(30),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);
        $slowTicket = Ticket::factory()->create([
            'created_at' => now()->subDays(7),
            'status' => 'open',
        ]);

        $fastResult = $service->predictTicketSatisfaction($fastTicket);
        $slowResult = $service->predictTicketSatisfaction($slowTicket);

        expect($fastResult['predicted_satisfaction'])->toBeGreaterThan($slowResult['predicted_satisfaction']);
    });

    test('identifies contributing (influencer) factors', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictTicketSatisfaction($ticket);

        expect($result['influencers'])->toBeArray();
        expect(count($result['influencers']))->toBeGreaterThan(0);
        expect($result['factor_scores'])->toHaveCount(5);
    });

    test('predicts nps on a 0-10 scale', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictNPS($ticket);

        expect($result['predicted_nps_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });

    test('categorizes nps as promoter, passive or detractor', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictNPS($ticket);

        expect($result['nps_category'])->toBeIn(['promoter', 'passive', 'detractor']);
    });

    test('predicts ces on a 1-7 scale', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'resolved_at' => now()]);

        $result = $service->predictCES($ticket);

        expect($result['predicted_ces_score'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(7);
    });

    test('constructing the service with an invalid company id throws', function () {
        expect(fn () => new SatisfactionPredictionService(0))
            ->toThrow(\Modules\Shared\Exceptions\TenantException::class);
    });

    test('handles unresolved tickets without error', function () {
        $service = new SatisfactionPredictionService(1);
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        $result = $service->predictTicketSatisfaction($ticket);

        expect($result)->not->toHaveKey('error');
        expect($result['predicted_satisfaction'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });
});

// NOTE: A `describe('AgentPerformanceService', ...)` block previously lived here, testing a
// `Modules\Helpdesk\Services\AgentPerformanceService` class that was never actually built (no
// such file exists anywhere in the repo — `app(AgentPerformanceService::class)` threw
// BindingResolutionException on every one of its 8 tests). Because this file's name
// (`ServiceTests.php`) doesn't match PHPUnit's default `*Test.php` discovery suffix, the block
// was silently excluded from the normal test run and never surfaced as a failure. The real,
// live "agent talent management" feature (per-agent metrics/trend/skills/benchmarking, coaching
// recommendations, SMART goals via `PerformanceGoal`, development plans) is
// `Modules\Helpdesk\Services\AgentPerformanceAnalyticsService` behind `AgentPerformanceController`
// (19 routed endpoints) plus `CustomerServiceAIController`'s coaching/skills/trends endpoints —
// both fully tested by the real, passing `Modules/Helpdesk/tests/Feature/AgentPerformanceTest.php`.
// The dead block testing the phantom class was removed rather than fixed in place.
