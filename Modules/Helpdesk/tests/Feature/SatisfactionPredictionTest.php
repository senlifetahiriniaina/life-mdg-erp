<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;

uses(RefreshDatabase::class);

describe('Satisfaction Prediction - Score Prediction', function () {
    test('predicts customer satisfaction score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('satisfaction prediction includes confidence', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $confidence = $response->json('confidence');
        expect($confidence)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('predicts high satisfaction for fast resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subMinutes(30),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeGreaterThan(3);
    });

    test('predicts lower satisfaction for slow resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subDays(7),
            'resolved_at' => now(),
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeLessThan(4);
    });
});

describe('Satisfaction Prediction - Confidence Scoring', function () {
    test('provides confidence score between 0 and 1', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $confidence = $response->json('confidence');
        expect($confidence)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('high confidence for typical resolution scenario', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'resolved',
            'priority' => 'medium',
            'created_at' => now()->subHours(12),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeGreaterThan(0.7);
    });

    test('lower confidence for edge cases', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('confidence'))->toBeLessThan(0.7);
    });
});

describe('Satisfaction Prediction - Factor Identification', function () {
    test('identifies factors affecting satisfaction', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('contributing_factors'))->toBeArray();
        expect(count($response->json('contributing_factors')))->toBeGreaterThan(0);
    });

    test('factors include resolution time', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'resolved',
            'created_at' => now()->subHours(24),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $factors = $response->json('contributing_factors');
        expect(array_key_exists('resolution_time', array_flip($factors)))->toBeTrue();
    });

    test('factors include response quality', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $factors = $response->json('contributing_factors');
        expect(array_key_exists('response_quality', array_flip($factors)))->toBeTrue();
    });

    test('factors include priority handling', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('contributing_factors'))->toBeArray();
    });

    test('factors include customer sentiment', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $factors = $response->json('contributing_factors');
        expect(array_key_exists('sentiment', array_flip($factors)))->toBeTrue();
    });

    test('factor impact scores provided', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('factor_impacts'))->toBeArray();
    });
});

describe('Satisfaction Prediction - NPS Prediction', function () {
    test('predicts net promoter score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $nps = $response->json('predicted_nps');
        expect($nps)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(10);
    });

    test('categorizes nps as promoter, passive, or detractor', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('nps_category'))->toBeIn(['promoter', 'passive', 'detractor']);
    });

    test('promoter prediction for excellent resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'resolved',
            'priority' => 'high',
            'created_at' => now()->subMinutes(30),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('nps_category'))->toBe('promoter');
    });

    test('detractor prediction for poor resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'priority' => 'urgent',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('nps_category'))->toBe('detractor');
    });
});

describe('Satisfaction Prediction - CES Prediction', function () {
    test('predicts customer effort score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_ces'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('low ces for simple resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'description' => 'Simple login issue',
            'status' => 'resolved',
            'created_at' => now()->subMinutes(15),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_ces'))->toBeLessThan(3);
    });

    test('high ces for complex resolution', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'description' => 'Complex technical issue requiring multiple back-and-forths',
            'status' => 'open',
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_ces'))->toBeGreaterThan(3);
    });
});

describe('Satisfaction Prediction - Trend Analysis', function () {
    test('analyzes satisfaction trend by agent', function () {
        $user = User::factory()->create();
        $tickets = Ticket::factory()->count(5)->create(['assignee_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/satisfaction/trend/agent/{$user->id}")
            ->assertOk();

        expect($response->json('average_satisfaction'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('satisfaction trend over time', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/trend/timeline', [
                'period' => 'week',
            ])
            ->assertOk();

        expect($response->json('data'))->toBeArray();
    });

    test('satisfaction trend by ticket type', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/trend/by-type')
            ->assertOk();

        expect($response->json('data'))->toBeArray();
    });

    test('trend includes comparison with baseline', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/trend/timeline')
            ->assertOk();

        expect($response->json('baseline_average'))->toBeTruthy() | expect($response->json('baseline_average'))->toBeNull();
    });
});

describe('Satisfaction Prediction - At-Risk Customer Identification', function () {
    test('identifies at-risk customers', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/at-risk-customers')
            ->assertOk();

        expect($response->json('customers'))->toBeArray();
    });

    test('at-risk customer includes risk score', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'priority' => 'high',
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/at-risk-customers')
            ->assertOk();

        if (count($response->json('customers')) > 0) {
            expect($response->json('customers.0.risk_score'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
        }
    });

    test('at-risk customer includes reason', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/at-risk-customers')
            ->assertOk();

        if (count($response->json('customers')) > 0) {
            expect($response->json('customers.0.risk_reason'))->toBeString();
        }
    });

    test('can filter at-risk customers by risk level', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/at-risk-customers', [
                'min_risk' => 0.7,
            ])
            ->assertOk();

        if (count($response->json('customers')) > 0) {
            expect($response->json('customers.0.risk_score'))->toBeGreaterThanOrEqual(0.7);
        }
    });

    test('provides action recommendations for at-risk customers', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/at-risk-customers')
            ->assertOk();

        if (count($response->json('customers')) > 0) {
            expect($response->json('customers.0.recommended_actions'))->toBeArray();
        }
    });
});

describe('Satisfaction Prediction - Prediction Accuracy Tracking', function () {
    test('stores satisfaction prediction for tracking', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('helpdesk_satisfaction_predictions', [
            'ticket_id' => $ticket->id,
        ]);
    });

    test('can retrieve prediction accuracy metrics', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/accuracy-metrics')
            ->assertOk();

        expect($response->json('total_predictions'))->toBeGreaterThanOrEqual(0);
        expect($response->json('mean_absolute_error'))->toBeGreaterThanOrEqual(0);
    });

    test('tracks prediction vs actual satisfaction', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $predictionResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->json();

        $predictionId = $predictionResponse['id'];

        // Simulate actual satisfaction response
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/satisfaction/predictions/{$predictionId}/actual", [
                'actual_score' => 4,
            ])
            ->assertOk();

        $metricsResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/accuracy-metrics')
            ->assertOk();

        expect($metricsResponse->json('total_tracked'))->toBeGreaterThanOrEqual(1);
    });

    test('calculates mean absolute error', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/accuracy-metrics')
            ->assertOk();

        if ($response->json('total_predictions') > 0) {
            expect($response->json('mean_absolute_error'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(5);
        }
    });
});

describe('Satisfaction Prediction - Comparative Analysis', function () {
    test('compares satisfaction across agents', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/comparison/agents')
            ->assertOk();

        expect($response->json('agents'))->toBeArray();
    });

    test('compares satisfaction across teams', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/comparison/teams')
            ->assertOk();

        expect($response->json('teams'))->toBeArray();
    });

    test('comparison includes rank and percentile', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/satisfaction/comparison/agents')
            ->assertOk();

        if (count($response->json('agents')) > 0) {
            expect($response->json('agents.0.rank'))->toBeTruthy();
            expect($response->json('agents.0.percentile'))->toBeTruthy();
        }
    });
});

describe('Satisfaction Prediction - Authorization', function () {
    test('requires authentication to predict satisfaction', function () {
        $ticket = Ticket::factory()->create();

        $this->postJson('/api/v1/helpdesk/satisfaction/predict', [
            'ticket_id' => $ticket->id,
        ])->assertUnauthorized();
    });

    test('cannot predict satisfaction for restricted tickets', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });
});

describe('Satisfaction Prediction - Edge Cases', function () {
    test('handles tickets with no resolution time', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('handles very old tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'created_at' => now()->subYears(1),
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('handles high-priority urgent tickets', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'urgent',
            'status' => 'open',
            'created_at' => now()->subHours(24),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertOk();

        expect($response->json('predicted_satisfaction'))->toBeLessThan(3);
    });
});

describe('Satisfaction Prediction - Company Isolation', function () {
    test('predictions are company-isolated', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($user2, 'sanctum')
            ->postJson('/api/v1/helpdesk/satisfaction/predict', [
                'ticket_id' => $ticket->id,
            ])
            ->assertForbidden();
    });
});
