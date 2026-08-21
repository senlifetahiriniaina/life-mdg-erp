<?php

declare(strict_types=1);

use App\Models\User;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Ticket;


test('authenticated user can list tickets', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/helpdesk/tickets')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('authenticated user can create a ticket', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'Cannot login to dashboard',
            'description' => 'I get a 403 error.',
            'priority' => 'high',
            'channel' => 'web',
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', 'open');
});

test('ticket creation applies default SLA policy when present', function () {
    $user = User::factory()->create();
    $policy = SlaPolicy::factory()->create(['is_default' => true, 'response_time_hours' => 4]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/tickets', [
            'subject' => 'SLA Test Ticket',
            'priority' => 'medium',
        ])
        ->assertStatus(201);

    expect($response->json('sla_id'))->toBe($policy->id);
    expect($response->json('sla_due_at'))->not->toBeNull();
});

test('authenticated user can resolve a ticket', function () {
    // Chantier 32.21: TicketController::resolve() previously had zero
    // authorize() call at all (a confirmed RBAC hole — any authenticated
    // user could resolve any ticket) — now gated on the closeTicket ability
    // (admin/manager/supervisor of the ticket's own company), so this needs
    // a real role rather than a bare unroled user.
    $user = actingAsUser('admin');
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/resolve")
        ->assertOk()
        ->assertJsonPath('status', 'resolved');
});

test('authenticated user can escalate a ticket', function () {
    // Chantier 32.21: escalate() previously had zero authorize() call —
    // now gated on the update ability, which a bare unroled user (not the
    // ticket's own reporter) never passes.
    $user = actingAsUser('admin');
    $ticket = Ticket::factory()->create(['status' => 'open', 'priority' => 'low']);

    $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/escalate")
        ->assertOk()
        ->assertJsonPath('priority', 'urgent')
        ->assertJsonPath('sla_breached', true);
});

test('unauthenticated user cannot list tickets', function () {
    $this->getJson('/api/v1/helpdesk/tickets')
        ->assertUnauthorized();
});
