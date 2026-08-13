<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\SentimentAnalysisService;
use Modules\Helpdesk\Services\PredictiveEscalationService;
use Modules\Helpdesk\Services\AiResponseService;
use Modules\Helpdesk\Services\SatisfactionPredictionService;
use Modules\Helpdesk\Services\AgentPerformanceService;

uses(RefreshDatabase::class);

describe('SentimentAnalysisService', function () {
    test('analyzes positive sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('This is absolutely amazing!');

        expect($result['sentiment'])->toBe('positive');
        expect($result['confidence'])->toBeGreaterThan(0.8);
    });

    test('analyzes negative sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('This is terrible and awful!');

        expect($result['sentiment'])->toBe('negative');
        expect($result['confidence'])->toBeGreaterThan(0.8);
    });

    test('analyzes neutral sentiment correctly', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('The product arrived on time.');

        expect($result['sentiment'])->toBe('neutral');
    });

    test('detects emotions in text', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('I am very angry!', ['detect_emotions' => true]);

        expect($result['emotions'])->toBeArray();
        expect(count($result['emotions']))->toBeGreaterThan(0);
    });

    test('detects anger emotion specifically', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('I am furious!', ['detect_emotions' => true]);

        expect($result['emotions'])->toContain('anger');
    });

    test('provides emotion confidence scores', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('I am angry and frustrated!', ['detect_emotions' => true]);

        expect($result['emotion_scores'])->toBeArray();
    });

    test('analyzes multi-language text', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('¡Excelente!', ['language' => 'es']);

        expect($result['language'])->toBe('es');
        expect($result['sentiment'])->toBe('positive');
    });

    test('auto-detects language', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('Magnifique!');

        expect($result['language'])->toBeTruthy();
    });

    test('returns sentiment score from -1 to 1', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('This is okay.');

        expect($result['score'])->toBeGreaterThanOrEqual(-1)->toBeLessThanOrEqual(1);
    });

    test('handles empty text gracefully', function () {
        $service = app(SentimentAnalysisService::class);

        expect(fn() => $service->analyze(''))
            ->toThrow(InvalidArgumentException::class);
    });

    test('handles very long text', function () {
        $service = app(SentimentAnalysisService::class);
        $longText = str_repeat('This is a test. ', 500);
        $result = $service->analyze($longText);

        expect($result['sentiment'])->toBeTruthy();
    });

    test('handles special characters and emojis', function () {
        $service = app(SentimentAnalysisService::class);
        $result = $service->analyze('Great! 👍 Amazing! 🎉');

        expect($result['sentiment'])->toBe('positive');
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

describe('SatisfactionPredictionService', function () {
    test('predicts satisfaction score', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['predicted_satisfaction'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('provides confidence score #3', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['confidence'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('predicts high satisfaction for fast resolution', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subMinutes(30),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);

        $result = $service->predict($ticket);

        expect($result['predicted_satisfaction'])->toBeGreaterThan(3);
    });

    test('identifies contributing factors', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['contributing_factors'])->toBeArray();
        expect(count($result['contributing_factors']))->toBeGreaterThan(0);
    });

    test('predicts nps', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['predicted_nps'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });

    test('categorizes nps', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['nps_category'])->toBeIn(['promoter', 'passive', 'detractor']);
    });

    test('predicts ces', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create();

        $result = $service->predict($ticket);

        expect($result['predicted_ces'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('handles edge cases', function () {
        $service = app(SatisfactionPredictionService::class);
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        $result = $service->predict($ticket);

        expect($result['predicted_satisfaction'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
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
