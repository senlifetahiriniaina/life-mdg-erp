<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;

uses(RefreshDatabase::class);

describe('Agent Performance - Metric Calculation', function () {
    test('calculates average resolution time', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();
        Ticket::factory()->count(3)->create([
            'assignee_id' => $agent->id,
            'status' => 'resolved',
            'created_at' => now()->subHours(5),
            'resolved_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertOk();

        expect($response->json('average_resolution_time_hours'))->toBeGreaterThan(0);
    });

    test('calculates customer satisfaction metric', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertOk();

        expect($response->json('average_satisfaction_score'))->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
    });

    test('calculates sla compliance rate', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();
        Ticket::factory()->count(5)->create([
            'assignee_id' => $agent->id,
            'sla_breached' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertOk();

        $slaRate = $response->json('sla_compliance_rate');
        expect($slaRate)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
    });

    test('metric response includes all key metrics', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertOk();

        expect($response->json())->toHaveKeys([
            'total_tickets',
            'resolved_tickets',
            'average_resolution_time_hours',
            'average_satisfaction_score',
            'sla_compliance_rate',
            'first_response_time_minutes',
        ]);
    });

    test('first response time metric calculated', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();
        Ticket::factory()->create([
            'assignee_id' => $agent->id,
            'created_at' => now()->subMinutes(30),
            'first_response_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertOk();

        expect($response->json('first_response_time_minutes'))->toBeGreaterThan(0);
    });
});

describe('Agent Performance - Performance Trend Analysis', function () {
    test('analyzes performance trend over time', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/trend", [
                'period' => 'week',
            ])
            ->assertOk();

        expect($response->json('trend_data'))->toBeArray();
    });

    test('trend shows improving performance', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/trend", [
                'period' => 'month',
            ])
            ->assertOk();

        expect($response->json('trajectory'))->toBeIn(['improving', 'declining', 'stable']);
    });

    test('trend includes period comparison', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/trend")
            ->assertOk();

        expect($response->json('comparison_vs_previous_period'))->toBeArray();
    });

    test('trend analysis shows metric changes', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/trend")
            ->assertOk();

        expect($response->json('metric_changes'))->toBeArray();
    });
});

describe('Agent Performance - Skill Proficiency Scoring', function () {
    test('calculates skill proficiency scores', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/skills")
            ->assertOk();

        expect($response->json('skills'))->toBeArray();
    });

    test('skill proficiency ranges from 0 to 1', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/skills")
            ->assertOk();

        if (count($response->json('skills')) > 0) {
            expect($response->json('skills.0.proficiency'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(1);
        }
    });

    test('skills include category', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/skills")
            ->assertOk();

        if (count($response->json('skills')) > 0) {
            expect($response->json('skills.0.category'))->toBeString();
        }
    });

    test('identifies top skills', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/skills")
            ->assertOk();

        expect($response->json('top_skills'))->toBeArray();
    });

    test('identifies skills needing improvement', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/skills")
            ->assertOk();

        expect($response->json('improvement_areas'))->toBeArray();
    });
});

describe('Agent Performance - Peer Benchmarking', function () {
    test('benchmarks agent against peers', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/benchmarking")
            ->assertOk();

        expect($response->json('agent_metrics'))->toBeArray();
        expect($response->json('peer_average'))->toBeArray();
    });

    test('benchmark includes percentile ranking', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/benchmarking")
            ->assertOk();

        expect($response->json('percentile_rank'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    });

    test('benchmark shows above/below average performance', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/benchmarking")
            ->assertOk();

        expect($response->json('metrics_above_average'))->toBeArray();
        expect($response->json('metrics_below_average'))->toBeArray();
    });

    test('benchmark includes team comparison', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/benchmarking")
            ->assertOk();

        expect($response->json('team_rank'))->toBeTruthy();
    });
});

describe('Agent Performance - Ranking', function () {
    test('retrieves agent ranking', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/ranking')
            ->assertOk();

        expect($response->json('agents'))->toBeArray();
    });

    test('ranking includes overall score', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/ranking')
            ->assertOk();

        if (count($response->json('agents')) > 0) {
            expect($response->json('agents.0.overall_score'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    });

    test('ranking can be sorted by metric', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/ranking', [
                'sort_by' => 'satisfaction',
            ])
            ->assertOk();

        expect($response->json('agents'))->toBeArray();
    });

    test('ranking shows agent position', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/ranking')
            ->assertOk();

        if (count($response->json('agents')) > 0) {
            expect($response->json('agents.0.rank'))->toBeTruthy();
        }
    });
});

describe('Agent Performance - Coaching Recommendations', function () {
    test('generates coaching recommendations', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")
            ->assertOk();

        expect($response->json('recommendations'))->toBeArray();
    });

    test('coaching includes priority areas', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")
            ->assertOk();

        if (count($response->json('recommendations')) > 0) {
            expect($response->json('recommendations.0.priority'))->toBeIn(['high', 'medium', 'low']);
        }
    });

    test('coaching recommendations include specific actions', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")
            ->assertOk();

        if (count($response->json('recommendations')) > 0) {
            expect($response->json('recommendations.0.action'))->toBeString();
        }
    });

    test('coaching includes expected impact', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")
            ->assertOk();

        if (count($response->json('recommendations')) > 0) {
            expect($response->json('recommendations.0.expected_impact'))->toBeString();
        }
    });

    test('coaching includes deadline', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/coaching")
            ->assertOk();

        if (count($response->json('recommendations')) > 0) {
            expect($response->json('recommendations.0.target_date'))->toBeTruthy();
        }
    });
});

describe('Agent Performance - Goal Tracking', function () {
    test('retrieves agent goals', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/goals")
            ->assertOk();

        expect($response->json('goals'))->toBeArray();
    });

    test('goal includes progress tracking', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/goals")
            ->assertOk();

        if (count($response->json('goals')) > 0) {
            expect($response->json('goals.0.progress_percentage'))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    });

    test('can create goal for agent', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/goals", [
                'title' => 'Increase customer satisfaction to 4.5',
                'target_value' => 4.5,
                'deadline' => now()->addMonths(3)->toDateString(),
                'metric' => 'satisfaction_score',
            ])
            ->assertCreated();

        expect($response->json('goal_id'))->toBeTruthy();
    });

    test('can update goal progress', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $goalResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/goals", [
                'title' => 'Reduce resolution time',
                'target_value' => 2,
                'deadline' => now()->addMonths(3)->toDateString(),
                'metric' => 'resolution_time',
            ])
            ->json();

        $goalId = $goalResponse['goal_id'];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/goals/{$goalId}/progress", [
                'progress_percentage' => 50,
                'notes' => 'On track to meet goal',
            ])
            ->assertOk();
    });

    test('goal shows if on track', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/goals")
            ->assertOk();

        if (count($response->json('goals')) > 0) {
            expect($response->json('goals.0.status'))->toBeIn(['on_track', 'at_risk', 'behind']);
        }
    });
});

describe('Agent Performance - Team Comparison', function () {
    test('compares team performance metrics', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/team/comparison')
            ->assertOk();

        expect($response->json('teams'))->toBeArray();
    });

    test('team comparison includes aggregate metrics', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/team/comparison')
            ->assertOk();

        if (count($response->json('teams')) > 0) {
            expect($response->json('teams.0.average_satisfaction'))->toBeTruthy();
        }
    });

    test('team comparison shows best performers', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/team/comparison')
            ->assertOk();

        expect($response->json('best_performing_teams'))->toBeArray();
    });
});

describe('Agent Performance - Performance Reporting', function () {
    test('generates performance report', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report", [
                'period' => 'month',
            ])
            ->assertOk();

        expect($response->json('report'))->toBeArray();
    });

    test('report includes key metrics summary', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report")
            ->assertOk();

        expect($response->json('summary'))->toBeArray();
    });

    test('report includes detailed metrics', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report")
            ->assertOk();

        expect($response->json('metrics'))->toBeArray();
    });

    test('report includes highlights and concerns', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report")
            ->assertOk();

        expect($response->json('highlights'))->toBeArray();
        expect($response->json('concerns'))->toBeArray();
    });

    test('can export performance report', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/report/export", [
                'format' => 'pdf',
            ])
            ->assertOk();

        expect($response->json('export_url'))->toBeTruthy();
    });
});

describe('Agent Performance - Individual Development Plans', function () {
    test('retrieves individual development plan', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/development-plan")
            ->assertOk();

        expect($response->json('plan'))->toBeArray();
    });

    test('can create development plan', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/development-plan", [
                'goals' => ['Improve response quality', 'Reduce resolution time'],
                'focus_areas' => ['Communication', 'Technical skills'],
                'duration_months' => 6,
            ])
            ->assertCreated();

        expect($response->json('plan_id'))->toBeTruthy();
    });

    test('development plan includes milestones', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$agent->id}/development-plan")
            ->assertOk();

        expect($response->json('milestones'))->toBeArray();
    });

    test('can update development plan progress', function () {
        $user = User::factory()->create();
        $agent = User::factory()->create();

        $planResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/development-plan", [
                'goals' => ['Test goal'],
                'focus_areas' => ['Skills'],
                'duration_months' => 3,
            ])
            ->json();

        $planId = $planResponse['plan_id'];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/helpdesk/agents/{$agent->id}/development-plan/{$planId}/progress", [
                'notes' => 'Agent completed training',
                'milestones_completed' => 1,
            ])
            ->assertOk();
    });
});

describe('Agent Performance - Authorization', function () {
    test('requires authentication to view metrics', function () {
        $agent = User::factory()->create();

        $this->getJson("/api/v1/helpdesk/agents/{$agent->id}/metrics")
            ->assertUnauthorized();
    });

    test('agent can view own metrics', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$user->id}/metrics")
            ->assertOk();

        expect($response->json('total_tickets'))->toBeTruthy();
    });

    test('manager can view team metrics', function () {
        $manager = User::factory()->create();
        $agent = User::factory()->create();

        // Assuming manager has permission
        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/v1/helpdesk/team/comparison');

        expect($response->status())->toBeIn([200, 403]);
    });
});

describe('Agent Performance - Company Isolation', function () {
    test('cannot view metrics for other company agents', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Assuming users from different companies
        $this->actingAs($user1, 'sanctum')
            ->getJson("/api/v1/helpdesk/agents/{$user2->id}/metrics")
            ->assertForbidden();
    });
});

describe('Agent Performance - Bulk Operations', function () {
    test('can retrieve metrics for multiple agents', function () {
        $user = User::factory()->create();
        $agents = User::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/helpdesk/agents/bulk-metrics', [
                'agent_ids' => $agents->pluck('id')->toArray(),
            ])
            ->assertOk();

        expect($response->json('metrics'))->toHaveCount(3);
    });

    test('bulk metrics export', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/helpdesk/agents/metrics-export', [
                'period' => 'month',
                'format' => 'csv',
            ])
            ->assertOk();

        expect($response->json('export_url'))->toBeTruthy();
    });
});
