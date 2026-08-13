<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\CsatSurvey;
use Modules\Helpdesk\Models\Ticket;


test('can fetch csat report', function () {
    $user = User::factory()->create();

    // Create some surveys with scores
    $ticket = Ticket::factory()->create();
    CsatSurvey::factory()->count(3)->create([
        'ticket_id' => $ticket->id,
        'score' => 5,
        'responded_at' => now(),
    ]);
    CsatSurvey::factory()->create([
        'ticket_id' => $ticket->id,
        'score' => 2,
        'responded_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/csat/report')
        ->assertOk()
        ->assertJsonStructure([
            'score',
            'trend',
            'by_agent',
            'distribution',
            'comments',
            'period' => ['from', 'to'],
        ]);
});

test('csat score is correct percentage', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // 3 positive (>=4), 1 negative => 75%
    CsatSurvey::factory()->count(3)->create([
        'ticket_id' => $ticket->id,
        'score' => 4,
        'responded_at' => now(),
    ]);
    CsatSurvey::factory()->create([
        'ticket_id' => $ticket->id,
        'score' => 2,
        'responded_at' => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/csat/report')
        ->assertOk();

    $score = $response->json('score');
    expect($score)->toBe(75.0);
});

test('can list csat surveys', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    CsatSurvey::factory()->count(5)->create(['ticket_id' => $ticket->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/csat/surveys')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
});

test('can create a survey', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/csat/surveys', [
            'ticket_id' => $ticket->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('ticket_id', $ticket->id);

    $this->assertDatabaseHas('helpdesk_csat_surveys', [
        'ticket_id' => $ticket->id,
    ]);
});

test('can record a survey response', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $survey = CsatSurvey::factory()->create([
        'ticket_id' => $ticket->id,
        'score' => null,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/helpdesk/csat/surveys/{$survey->id}", [
            'score' => 5,
            'comment' => 'Excellent support, very fast!',
        ])
        ->assertOk()
        ->assertJsonPath('score', 5)
        ->assertJsonPath('comment', 'Excellent support, very fast!');

    $this->assertDatabaseHas('helpdesk_csat_surveys', [
        'id' => $survey->id,
        'score' => 5,
    ]);
});

test('can manage campaigns', function () {
    $user = User::factory()->create();

    // Create a campaign
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/csat/campaigns', [
            'name' => 'Post-resolution Survey',
            'trigger' => 'ticket_closed',
            'delay_hours' => 24,
            'question_text' => 'How satisfied are you with our support?',
            'active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Post-resolution Survey');

    $campaignId = $response->json('id');

    // List campaigns
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/csat/campaigns')
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', $campaignId);
});

test('score validation rejects out of range scores', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $survey = CsatSurvey::factory()->create(['ticket_id' => $ticket->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/helpdesk/csat/surveys/{$survey->id}", ['score' => 6])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/helpdesk/csat/surveys/{$survey->id}", ['score' => 0])
        ->assertStatus(422);
});
