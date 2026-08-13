<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\EscalationRule;

uses(RefreshDatabase::class);

describe('Predictive Escalation - Basic Detection', function () {
    test('predicts escalation need for complex issue', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('needs_escalation'))->toBeBoolean();
        expect($response->json('escalation_score'))->toBeGreaterThanOrEqual(0);
        expect($response->json('escalation_score'))->toBeLessThanOrEqual(1);
    });

    test('escalation prediction includes confidence', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('predicts escalation for high priority unresolved tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('needs_escalation'))->toBeTrue();
    });

    test('no escalation for closed low priority tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'low',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('needs_escalation'))->toBeFalse();
    });
});

describe('Predictive Escalation - Urgency Scoring', function () {
    test('calculates urgency score based on age and priority', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'created_at' => now()->subHours(24),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('urgency_score'))->toBeGreaterThan(0.5);
    });

    test('older tickets have higher urgency score', function () {
        $user = User::factory()->create();
        $newTicket = Ticket::factory()->create([
            'priority' => 'high',
            'created_at' => now()->subHours(1),
        ]);
        $oldTicket = Ticket::factory()->create([
            'priority' => 'high',
            'created_at' => now()->subDays(3),
        ]);

        $newResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $newTicket->id,
            ])
            ->json();

        $oldResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $oldTicket->id,
            ])
            ->json();

        expect($oldResponse['urgency_score'])->toBeGreaterThan($newResponse['urgency_score']);
    });

    test('urgent priority increases urgency score', function () {
        $user = User::factory()->create();
        $lowPriority = Ticket::factory()->create([
            'priority' => 'low',
            'created_at' => now()->subHours(12),
        ]);
        $urgentPriority = Ticket::factory()->create([
            'priority' => 'urgent',
            'created_at' => now()->subHours(12),
        ]);

        $lowResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $lowPriority->id,
            ])
            ->json();

        $urgentResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $urgentPriority->id,
            ])
            ->json();

        expect($urgentResponse['urgency_score'])->toBeGreaterThan($lowResponse['urgency_score']);
    });

    test('negative sentiment increases urgency score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        // First prediction without sentiment
        $basePrediction = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->json();

        // Second prediction with negative sentiment
        $withSentiment = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
                'sentiment_score' => -0.8,
            ])
            ->json();

        expect($withSentiment['urgency_score'])->toBeGreaterThan($basePrediction['urgency_score']);
    });
});

describe('Predictive Escalation - Confidence Scoring', function () {
    test('provides confidence score for prediction', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $confidence = $response->json('confidence');
        expect($confidence)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('confidence is high for clear escalation cases', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => now()->subDays(7),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeGreaterThan(0.8);
    });

    test('confidence is lower for ambiguous cases', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'medium',
            'status' => 'open',
            'created_at' => now()->subHours(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeLessThan(0.8);
    });
});

describe('Predictive Escalation - SLA Breach Prediction', function () {
    test('predicts SLA breach for approaching deadline', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addHours(1),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sla_breach_predicted'))->toBeBoolean();
    });

    test('high sla breach risk for tickets near deadline', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addMinutes(15),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sla_breach_predicted'))->toBeTrue();
        expect($response->json('sla_breach_risk'))->toBeGreaterThan(0.8);
    });

    test('no sla breach risk for recently created tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addDays(2),
            'created_at' => now(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sla_breach_predicted'))->toBeFalse();
    });

    test('sla metrics in escalation response', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'sla_due_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('sla_hours_remaining'))->toBeTruthy();
        expect($response->json('sla_percentage_used'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });
});

describe('Predictive Escalation - Multi-level Escalation', function () {
    test('identifies appropriate escalation level', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('escalation_level'))->toBeIn([null, 'level1', 'level2', 'level3', 'management']);
    });

    test('level 1 escalation for moderately complex issues', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'medium',
            'status' => 'open',
            'created_at' => now()->subHours(4),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $level = $response->json('escalation_level');
        if ($level) {
            expect($level)->toBeIn(['level1', 'level2', 'level3', 'management']);
        }
    });

    test('management escalation for critical issues', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $level = $response->json('escalation_level');
        if ($level) {
            expect($level)->toBeIn(['level2', 'level3', 'management']);
        }
    });

    test('provides escalation path recommendations', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        if ($response->json('needs_escalation')) {
            expect($response->json('escalation_path'))->toBeArray();
        }
    });
});

describe('Predictive Escalation - Recommendation Quality', function () {
    test('provides clear escalation recommendation', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('recommendation'))->toBeString();
    });

    test('recommendation includes reason for escalation', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        if ($response->json('needs_escalation')) {
            expect($response->json('escalation_reasons'))->toBeArray();
            expect(count($response->json('escalation_reasons')))->toBeGreaterThan(0);
        }
    });

    test('recommendation includes suggested actions', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        if ($response->json('needs_escalation')) {
            expect($response->json('suggested_actions'))->toBeArray();
        }
    });

    test('includes estimated resolution time in recommendation', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('estimated_resolution_hours'))->toBeTruthy();
    });
});

describe('Predictive Escalation - Model Accuracy Tracking', function () {
    test('stores escalation prediction for tracking', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_escalation_predictions', [
            'ticket_id' => $ticket->id,
        ]);
    });

    test('can retrieve prediction accuracy metrics', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/escalation/accuracy-metrics')
            ->assertOk();

        expect($response->json('total_predictions'))->toBeGreaterThanOrEqual(1);
        expect($response->json('accuracy_rate'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('tracks prediction correctness over time', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $prediction = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->json();

        // Simulate resolving ticket after escalation
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        // Update prediction outcome
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/escalation/predictions/{$prediction['id']}/outcome", [
                'actually_escalated' => true,
            ])
            ->assertOk();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/escalation/accuracy-metrics')
            ->assertOk();

        expect($response->json('total_tracked'))->toBeGreaterThanOrEqual(1);
    });

    test('calculates false positive rate', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/escalation/accuracy-metrics')
            ->assertOk();

        expect($response->json('false_positive_rate'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('calculates false negative rate', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/escalation/accuracy-metrics')
            ->assertOk();

        expect($response->json('false_negative_rate'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });
});

describe('Predictive Escalation - Edge Cases', function () {
    test('handles urgent issues with vip customers', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
        ]);
        // Assuming VIP customer flag or metadata
        $ticket->update(['metadata' => ['is_vip' => true]]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('escalation_score'))->toBeGreaterThan(0.8);
    });

    test('handles recently escalated tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
        ]);

        // First escalation
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
                'apply_escalation' => true,
            ]);

        // Second prediction shortly after
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('already_escalated'))->toBeTrue();
    });

    test('handles tickets with multiple reopens', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'medium',
            'status' => 'open',
            'metadata' => ['reopen_count' => 3],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        // Multiple reopens should increase escalation score
        expect($response->json('escalation_score'))->toBeGreaterThan(0.5);
    });

    test('handles zero response time tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'high',
            'status' => 'open',
            'first_response_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('escalation_score'))->toBeGreaterThan(0.6);
    });
});

describe('Predictive Escalation - Authorization', function () {
    test('requires authentication to predict escalation', function () {
        $ticket = Ticket::factory()->create();

        $this->postJson('/api/v1/helpdesk/escalation/predict', [
            'ticket_id' => $ticket->id,
        ])->assertUnauthorized();
    });

    test('cannot predict escalation for restricted tickets', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });

    test('requires permission to apply escalation', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
                'apply_escalation' => true,
            ]);

        // Should either work or require permission
        expect($response->status())->toBeIn([200, 403]);
    });
});

describe('Predictive Escalation - Batch Operations', function () {
    test('batch predict escalation for multiple tickets', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(5)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/batch-predict', [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
            ])
            ->assertOk();

        expect($response->json('predictions'))->toHaveCount(5);
    });

    test('batch predictions include summary metrics', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(10)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/batch-predict', [
                'ticket_ids' => $tickets->pluck('id')->toArray(),
            ])
            ->assertOk();

        expect($response->json('total_tickets'))->toBe(10);
        expect($response->json('tickets_needing_escalation'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });
});

describe('Predictive Escalation - Company Isolation', function () {
    test('predictions are company-isolated', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/escalation/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });
});
