<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Helpdesk\Models\EscalationRule;
use Modules\Helpdesk\Models\HelpdeskSlaPolicy;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\EscalationService;


test('admin can create SLA policy', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/sla-policies', [
            'name' => 'Standard SLA',
            'priority' => 'medium',
            'first_response_hours' => 4.0,
            'resolution_hours' => 24.0,
            'business_hours_only' => false,
            'is_default' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Standard SLA')
        ->assertJsonPath('is_default', true);
});

test('admin can create escalation rule', function () {
    $user = User::factory()->create();
    $policy = HelpdeskSlaPolicy::create([
        'name' => 'Basic',
        'priority' => 'medium',
        'first_response_hours' => 4,
        'resolution_hours' => 24,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/helpdesk/escalation-rules', [
            'name' => 'Auto-escalate after 8h',
            'sla_policy_id' => $policy->id,
            'trigger_type' => 'first_response_overdue',
            'trigger_hours' => 8.0,
            'action_type' => 'change_priority',
            'action_config' => ['priority' => 'high'],
            'is_active' => true,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Auto-escalate after 8h');
});

test('SLA breach calculation is correct', function () {
    $user = User::factory()->create();

    $ticket = Ticket::create([
        'subject' => 'Old open ticket',
        'reporter_id' => $user->id,
        'status' => 'open',
        'priority' => 'medium',
        'channel' => 'web',
        'sla_breached' => false,
        'sla_due_at' => now()->subHour(), // already past due
    ]);

    $service = app(EscalationService::class);
    $breach = $service->getSlaBreach($ticket);

    expect($breach['resolution_breached'])->toBeTrue()
        ->and($breach['breach_at'])->not->toBeNull();
});

test('ticket gets escalated when rule matches', function () {
    $user = User::factory()->create();

    // Create an old ticket (created 10 hours ago) using direct DB insert for timestamp control
    $tenHoursAgo = now()->subHours(10)->toDateTimeString();
    $ticketId = DB::table('hd_tickets')->insertGetId([
        'ticket_number' => 'HD-' . strtoupper(uniqid()),
        'subject' => 'Stale ticket',
        'reporter_id' => $user->id,
        'status' => 'open',
        'priority' => 'low',
        'channel' => 'web',
        'sla_breached' => false,
        'created_at' => $tenHoursAgo,
        'updated_at' => $tenHoursAgo,
    ]);
    $ticket = Ticket::findOrFail($ticketId);

    // Create a rule that matches (trigger after 8h of no first response)
    EscalationRule::create([
        'name' => 'First response overdue',
        'trigger_type' => 'first_response_overdue',
        'trigger_hours' => 8.0,
        'action_type' => 'change_priority',
        'action_config' => ['priority' => 'high'],
        'is_active' => true,
        'priority' => 0,
    ]);

    $service = app(EscalationService::class);
    $events = $service->checkAndEscalate($ticket);

    expect($events)->toHaveCount(1)
        ->and($events[0]->result)->toBe('success');

    $ticket->refresh();
    expect($ticket->priority)->toBe('high');
});
