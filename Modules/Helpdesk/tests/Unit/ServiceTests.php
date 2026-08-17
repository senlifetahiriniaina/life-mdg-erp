<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;
use Modules\Helpdesk\Services\SentimentAnalysisService;
use Modules\Helpdesk\Services\PredictiveEscalationService;
use Modules\Helpdesk\Services\AiResponseService;
use Modules\Helpdesk\Services\SatisfactionPredictionService;
use Modules\Helpdesk\Services\AgentPerformanceService;

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

describe('PredictiveEscalationService', function () {
    test('predicts escalation need', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $result = $service->predict($ticket);

        expect($result['needs_escalation'])->toBeBoolean();
        expect($result['escalation_score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('escalation score increases with age', function () {
        $service = app(PredictiveEscalationService::class);
        $newTicket = Ticket::factory()->create(['created_at' => now()->subHours(1)]);
        $oldTicket = Ticket::factory()->create(['created_at' => now()->subDays(3)]);

        $newResult = $service->predict($newTicket);
        $oldResult = $service->predict($oldTicket);

        expect($oldResult['escalation_score'])->toBeGreaterThan($newResult['escalation_score']);
    });

    test('escalation score increases with priority', function () {
        $service = app(PredictiveEscalationService::class);
        $lowPriority = Ticket::factory()->create(['priority' => 'low']);
        $urgentPriority = Ticket::factory()->create(['priority' => 'urgent']);

        $lowResult = $service->predict($lowPriority);
        $urgentResult = $service->predict($urgentPriority);

        expect($urgentResult['escalation_score'])->toBeGreaterThan($lowResult['escalation_score']);
    });

    test('predicts sla breach', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addMinutes(15),
            'status' => 'open',
        ]);

        $result = $service->predict($ticket);

        expect($result['sla_breach_predicted'])->toBeBoolean();
    });

    test('identifies escalation level', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        $result = $service->predict($ticket);

        expect($result['escalation_level'])->toBeIn([null, 'level1', 'level2', 'level3', 'management']);
    });

    test('provides escalation reasons', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'created_at' => now()->subDays(2),
        ]);

        $result = $service->predict($ticket);

        expect($result['escalation_reasons'])->toBeArray();
    });

    test('provides confidence score', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['confidence'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('handles recently escalated tickets', function () {
        $service = app(PredictiveEscalationService::class);
        $ticket = Ticket::factory()->create([
            'metadata' => ['escalated_at' => now()->subMinutes(5)],
        ]);

        $result = $service->predict($ticket);

        expect($result['already_escalated'])->toBeBoolean();
    });
});

describe('AiResponseService', function () {
    test('generates response suggestion', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create([
            'subject' => 'Cannot login',
            'description' => 'I cannot access my account',
        ]);

        $result = $service->suggest($ticket);

        expect($result['response_text'])->toBeString();
        expect(strlen($result['response_text']))->toBeGreaterThan(10);
    });

    test('generates contextual response', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create([
            'subject' => 'Billing issue',
            'description' => 'Why was I charged twice?',
        ]);

        $result = $service->suggest($ticket);

        $text = strtolower($result['response_text']);
        expect($text)->toContain('bill') | expect($text)->toContain('charge');
    });

    test('respects tone parameter', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $formalResult = $service->suggest($ticket, ['tone' => 'formal']);
        $friendlyResult = $service->suggest($ticket, ['tone' => 'friendly']);

        expect($formalResult['tone'])->toBe('formal');
        expect($friendlyResult['tone'])->toBe('friendly');
    });

    test('generates variants', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->variants($ticket, 3);

        expect($result['variants'])->toHaveCount(3);
    });

    test('variants are distinct', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->variants($ticket, 3);

        $texts = array_map(fn($v) => $v['text'], $result['variants']);
        expect(count(array_unique($texts)))->toBe(3);
    });

    test('variants are ranked by score', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->variants($ticket, 3);

        $scores = array_map(fn($v) => $v['score'], $result['variants']);
        expect($scores[0])->toBeGreaterThanOrEqual($scores[1]);
    });

    test('personalizes response', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create([
            'metadata' => ['customer_name' => 'John Doe'],
        ]);

        $result = $service->suggest($ticket, ['personalize' => true]);

        expect($result['response_text'])->toContain('John');
    });

    test('provides confidence score #2', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->suggest($ticket);

        expect($result['confidence'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('generates multi-language responses', function () {
        $service = app(AiResponseService::class);
        $ticket = Ticket::factory()->create();

        $enResult = $service->suggest($ticket, ['language' => 'en']);
        $esResult = $service->suggest($ticket, ['language' => 'es']);

        expect($enResult['language'])->toBe('en');
        expect($esResult['language'])->toBe('es');
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

describe('AgentPerformanceService', function () {
    test('calculates resolution time', function () {
        $service = app(AgentPerformanceService::class);
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subHours(5),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);

        $metrics = $service->calculateMetrics($ticket->assignee);

        expect($metrics['average_resolution_time_hours'])->toBeGreaterThan(0);
    });

    test('calculates satisfaction score', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $metrics = $service->calculateMetrics($user);

        expect($metrics['average_satisfaction_score'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('calculates sla compliance', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $metrics = $service->calculateMetrics($user);

        expect($metrics['sla_compliance_rate'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('calculates first response time', function () {
        $service = app(AgentPerformanceService::class);
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subMinutes(30),
            'first_response_at' => now(),
        ]);

        $metrics = $service->calculateMetrics($ticket->assignee);

        expect($metrics['first_response_time_minutes'])->toBeGreaterThan(0);
    });

    test('analyzes trend', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $trend = $service->analyzeTrend($user, 'month');

        expect($trend['trajectory'])->toBeIn(['improving', 'declining', 'stable']);
    });

    test('calculates skill proficiency', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $skills = $service->calculateSkillProficiency($user);

        expect($skills)->toBeArray();
    });

    test('benchmarks agent', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $benchmark = $service->benchmark($user);

        expect($benchmark['percentile_rank'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });

    test('generates coaching recommendations', function () {
        $service = app(AgentPerformanceService::class);
        $user = User::factory()->create();

        $recommendations = $service->generateCoachingRecommendations($user);

        expect($recommendations)->toBeArray();
    });
});
