<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Helpdesk\Models\SlaBreach;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Services\SlaAutomationService;


// ─── SlaPolicy model methods ───────────────────────────────────────────────

test('SlaPolicy isActive returns true when is_active is true', function () {
    $policy = SlaPolicy::factory()->create(['is_active' => true]);
    expect($policy->isActive())->toBeTrue();
});

test('SlaPolicy isActive returns false when is_active is false', function () {
    $policy = SlaPolicy::factory()->create(['is_active' => false]);
    expect($policy->isActive())->toBeFalse();
});

test('SlaPolicy responseDeadline adds response_time_minutes to startedAt', function () {
    $policy = SlaPolicy::factory()->create(['response_time_minutes' => 60]);
    $startedAt = Carbon::now();
    $deadline = $policy->responseDeadline($startedAt);

    expect((int) $startedAt->diffInMinutes($deadline))->toBe(60);
});

test('SlaPolicy resolutionDeadline adds resolution_time_minutes to startedAt', function () {
    $policy = SlaPolicy::factory()->create(['resolution_time_minutes' => 480]);
    $startedAt = Carbon::now();
    $deadline = $policy->resolutionDeadline($startedAt);

    expect((int) $startedAt->diffInMinutes($deadline))->toBe(480);
});

test('SlaPolicy isBreached returns true when deadline is in the past', function () {
    $policy = SlaPolicy::factory()->create();
    $deadline = Carbon::now()->subMinutes(5);

    expect($policy->isBreached($deadline))->toBeTrue();
});

test('SlaPolicy isBreached returns false when deadline is in the future', function () {
    $policy = SlaPolicy::factory()->create();
    $deadline = Carbon::now()->addMinutes(30);

    expect($policy->isBreached($deadline))->toBeFalse();
});

test('SlaPolicy minutesUntilBreach returns negative value for past deadline', function () {
    $policy = SlaPolicy::factory()->create();
    $deadline = Carbon::now()->subMinutes(10);

    expect($policy->minutesUntilBreach($deadline))->toBeLessThan(0);
});

test('SlaPolicy minutesUntilBreach returns positive value for future deadline', function () {
    $policy = SlaPolicy::factory()->create();
    $deadline = Carbon::now()->addMinutes(30);

    expect($policy->minutesUntilBreach($deadline))->toBeGreaterThan(0);
});

// ─── SlaBreach model methods ───────────────────────────────────────────────

test('SlaBreach isAcknowledged returns false when acknowledged_at is null', function () {
    $breach = SlaBreach::factory()->create(['acknowledged_at' => null]);
    expect($breach->isAcknowledged())->toBeFalse();
});

test('SlaBreach isAcknowledged returns true when acknowledged_at is set', function () {
    $breach = SlaBreach::factory()->create(['acknowledged_at' => now()]);
    expect($breach->isAcknowledged())->toBeTrue();
});

test('SlaBreach acknowledge sets acknowledged_at', function () {
    $breach = SlaBreach::factory()->create(['acknowledged_at' => null]);
    $breach->acknowledge();
    $breach->refresh();

    expect($breach->acknowledged_at)->not->toBeNull();
});

test('SlaBreach escalate sets escalated and escalated_at', function () {
    $breach = SlaBreach::factory()->create(['escalated' => false, 'escalated_at' => null]);
    $breach->escalate();
    $breach->refresh();

    expect($breach->escalated)->toBeTrue()
        ->and($breach->escalated_at)->not->toBeNull();
});

test('SlaBreach needsEscalation returns true when threshold passed', function () {
    $breach = SlaBreach::factory()->create([
        'escalated' => false,
        'breached_at' => now()->subMinutes(60),
    ]);

    expect($breach->needsEscalation(30))->toBeTrue();
});

test('SlaBreach needsEscalation returns false when already escalated', function () {
    $breach = SlaBreach::factory()->create([
        'escalated' => true,
        'breached_at' => now()->subMinutes(60),
    ]);

    expect($breach->needsEscalation(30))->toBeFalse();
});

test('SlaBreach needsEscalation returns false when threshold not yet reached', function () {
    $breach = SlaBreach::factory()->create([
        'escalated' => false,
        'breached_at' => now()->subMinutes(5),
    ]);

    expect($breach->needsEscalation(30))->toBeFalse();
});

// ─── SlaAutomationService ─────────────────────────────────────────────────

test('getPolicyForPriority returns matching active policy', function () {
    SlaPolicy::factory()->create(['priority' => 'high', 'is_active' => true]);
    $service = app(SlaAutomationService::class);

    expect($service->getPolicyForPriority('high'))->not->toBeNull();
});

test('getPolicyForPriority returns null when no policy exists for priority', function () {
    $service = app(SlaAutomationService::class);
    expect($service->getPolicyForPriority('critical'))->toBeNull();
});

test('getPolicyForPriority returns null for inactive policies', function () {
    SlaPolicy::factory()->create(['priority' => 'low', 'is_active' => false]);
    $service = app(SlaAutomationService::class);

    expect($service->getPolicyForPriority('low'))->toBeNull();
});

test('checkAndRecordBreaches returns empty array when no policy found', function () {
    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(1, 'urgent', now()->subHours(2), null, null);

    expect($breaches)->toBe([]);
});

test('checkAndRecordBreaches records response breach when no first response and deadline passed', function () {
    SlaPolicy::factory()->create([
        'priority' => 'normal',
        'response_time_minutes' => 30,
        'resolution_time_minutes' => 480,
        'is_active' => true,
    ]);

    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(
        ticketId: 42,
        priority: 'normal',
        createdAt: now()->subMinutes(60),
        firstResponseAt: null,
        resolvedAt: null,
    );

    expect($breaches)->toHaveCount(1)
        ->and($breaches[0]->breach_type)->toBe('response');
});

test('checkAndRecordBreaches records both breaches when ticket is very old and unresolved', function () {
    SlaPolicy::factory()->create([
        'priority' => 'high',
        'response_time_minutes' => 30,
        'resolution_time_minutes' => 60,
        'is_active' => true,
    ]);

    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(
        ticketId: 10,
        priority: 'high',
        createdAt: now()->subMinutes(120),
        firstResponseAt: null,
        resolvedAt: null,
    );

    expect($breaches)->toHaveCount(2);
    $types = array_map(fn ($b) => $b->breach_type, $breaches);
    expect($types)->toContain('response')
        ->toContain('resolution');
});

test('checkAndRecordBreaches records resolution breach when resolved late', function () {
    SlaPolicy::factory()->create([
        'priority' => 'critical',
        'response_time_minutes' => 15,
        'resolution_time_minutes' => 60,
        'is_active' => true,
    ]);

    $createdAt = now()->subMinutes(90);
    $resolvedAt = now()->subMinutes(5); // resolved after 85 mins, deadline was 60

    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(
        ticketId: 7,
        priority: 'critical',
        createdAt: $createdAt,
        firstResponseAt: $createdAt->copy()->addMinutes(10),
        resolvedAt: $resolvedAt,
    );

    expect($breaches)->toHaveCount(1)
        ->and($breaches[0]->breach_type)->toBe('resolution');
});

test('checkAndRecordBreaches records response breach when first response was late', function () {
    SlaPolicy::factory()->create([
        'priority' => 'urgent',
        'response_time_minutes' => 30,
        'resolution_time_minutes' => 240,
        'is_active' => true,
    ]);

    $createdAt = now()->subMinutes(90);
    $firstResponseAt = $createdAt->copy()->addMinutes(60); // responded at 60 min, deadline 30

    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(
        ticketId: 5,
        priority: 'urgent',
        createdAt: $createdAt,
        firstResponseAt: $firstResponseAt,
        resolvedAt: now(),
    );

    expect($breaches)->toHaveCount(1)
        ->and($breaches[0]->breach_type)->toBe('response');
});

test('checkAndRecordBreaches returns empty when within SLA', function () {
    SlaPolicy::factory()->create([
        'priority' => 'low',
        'response_time_minutes' => 480,
        'resolution_time_minutes' => 2880,
        'is_active' => true,
    ]);

    $createdAt = now()->subMinutes(30);

    $service = app(SlaAutomationService::class);
    $breaches = $service->checkAndRecordBreaches(
        ticketId: 3,
        priority: 'low',
        createdAt: $createdAt,
        firstResponseAt: $createdAt->copy()->addMinutes(20),
        resolvedAt: null,
    );

    expect($breaches)->toBe([]);
});

test('getPendingBreaches returns only unacknowledged breaches', function () {
    SlaBreach::factory()->create(['acknowledged_at' => null]);
    SlaBreach::factory()->create(['acknowledged_at' => now()]);

    $service = app(SlaAutomationService::class);
    $pending = $service->getPendingBreaches();

    expect($pending)->toHaveCount(1);
});

test('getTicketBreaches returns breaches for specific ticket', function () {
    SlaBreach::factory()->create(['ticket_id' => 100]);
    SlaBreach::factory()->create(['ticket_id' => 100]);
    SlaBreach::factory()->create(['ticket_id' => 200]);

    $service = app(SlaAutomationService::class);
    $breaches = $service->getTicketBreaches(100);

    expect($breaches)->toHaveCount(2);
});

test('runEscalations escalates qualifying breaches', function () {
    $policy = SlaPolicy::factory()->create([
        'escalation_enabled' => true,
        'escalation_after_minutes' => 30,
    ]);

    SlaBreach::factory()->create([
        'policy_id' => $policy->id,
        'escalated' => false,
        'breached_at' => now()->subMinutes(60),
    ]);

    $service = app(SlaAutomationService::class);
    $count = $service->runEscalations();

    expect($count)->toBe(1);
});

test('runEscalations does not escalate already escalated breaches', function () {
    $policy = SlaPolicy::factory()->create([
        'escalation_enabled' => true,
        'escalation_after_minutes' => 10,
    ]);

    SlaBreach::factory()->create([
        'policy_id' => $policy->id,
        'escalated' => true,
        'escalated_at' => now()->subMinutes(30),
        'breached_at' => now()->subMinutes(60),
    ]);

    $service = app(SlaAutomationService::class);
    $count = $service->runEscalations();

    expect($count)->toBe(0);
});

test('runEscalations does not escalate when threshold not yet reached', function () {
    $policy = SlaPolicy::factory()->create([
        'escalation_enabled' => true,
        'escalation_after_minutes' => 120,
    ]);

    SlaBreach::factory()->create([
        'policy_id' => $policy->id,
        'escalated' => false,
        'breached_at' => now()->subMinutes(10),
    ]);

    $service = app(SlaAutomationService::class);
    $count = $service->runEscalations();

    expect($count)->toBe(0);
});

test('getComplianceStats returns expected keys', function () {
    $service = app(SlaAutomationService::class);
    $stats = $service->getComplianceStats();

    expect($stats)->toHaveKeys(['total_tickets_checked', 'compliant', 'breached', 'compliance_rate']);
});

test('getPerformanceReport returns array', function () {
    $policy = SlaPolicy::factory()->create(['priority' => 'high']);
    SlaBreach::factory()->create([
        'policy_id' => $policy->id,
        'breach_type' => 'response',
    ]);

    $service = app(SlaAutomationService::class);
    $report = $service->getPerformanceReport();

    expect($report)->toBeArray();
    if (count($report) > 0) {
        expect($report[0])->toHaveKeys(['priority', 'avg_response_minutes', 'avg_resolution_minutes', 'breach_count']);
    }
});

// ─── API Endpoints ────────────────────────────────────────────────────────

test('unauthenticated cannot access SLA policies', function () {
    auth()->logout();
    $this->getJson('/api/v1/helpdesk/sla/policies')->assertStatus(401);
});

test('admin can list SLA policies', function () {
    actingAsUser('admin');
    SlaPolicy::factory()->count(3)->create(['is_active' => true]);

    $this->getJson('/api/v1/helpdesk/sla/policies')
        ->assertOk()
        ->assertJsonCount(3);
});

test('admin can create SLA policy', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/helpdesk/sla/policies', [
        'name' => 'Premium SLA',
        'description' => 'Fast response times',
        'priority' => 'critical',
        'response_time_minutes' => 15,
        'resolution_time_minutes' => 120,
        'business_hours_only' => false,
        'escalation_enabled' => true,
        'escalation_after_minutes' => 30,
        'is_active' => true,
    ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Premium SLA')
        ->assertJsonPath('priority', 'critical');
});

test('create SLA policy validates required fields', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/helpdesk/sla/policies', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'priority', 'response_time_minutes', 'resolution_time_minutes']);
});

test('create SLA policy validates priority enum', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/helpdesk/sla/policies', [
        'name' => 'Test',
        'priority' => 'invalid_priority',
        'response_time_minutes' => 60,
        'resolution_time_minutes' => 480,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['priority']);
});

test('admin can show a SLA policy', function () {
    actingAsUser('admin');
    $policy = SlaPolicy::factory()->create();

    $this->getJson("/api/v1/helpdesk/sla/policies/{$policy->id}")
        ->assertOk()
        ->assertJsonPath('id', $policy->id);
});

test('admin can update a SLA policy', function () {
    actingAsUser('admin');
    $policy = SlaPolicy::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/v1/helpdesk/sla/policies/{$policy->id}", [
        'name' => 'New Name',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'New Name');
});

test('admin can delete a SLA policy', function () {
    actingAsUser('admin');
    $policy = SlaPolicy::factory()->create();

    $this->deleteJson("/api/v1/helpdesk/sla/policies/{$policy->id}")
        ->assertStatus(204);

    expect(SlaPolicy::find($policy->id))->toBeNull();
});

test('admin can check breaches for a ticket', function () {
    actingAsUser('admin');

    SlaPolicy::factory()->create([
        'priority' => 'high',
        'response_time_minutes' => 30,
        'resolution_time_minutes' => 120,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/helpdesk/sla/check', [
        'ticket_id' => 1,
        'priority' => 'high',
        'created_at' => now()->subMinutes(90)->toDateTimeString(),
    ])
        ->assertOk()
        ->assertJsonIsArray();
});

test('check breaches validates required fields', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/helpdesk/sla/check', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ticket_id', 'priority', 'created_at']);
});

test('admin can get pending breaches', function () {
    actingAsUser('admin');
    SlaBreach::factory()->count(2)->create(['acknowledged_at' => null]);
    SlaBreach::factory()->create(['acknowledged_at' => now()]);

    $this->getJson('/api/v1/helpdesk/sla/breaches/pending')
        ->assertOk()
        ->assertJsonCount(2);
});

test('admin can get ticket breaches', function () {
    actingAsUser('admin');
    SlaBreach::factory()->count(3)->create(['ticket_id' => 55]);
    SlaBreach::factory()->create(['ticket_id' => 99]);

    $this->getJson('/api/v1/helpdesk/sla/breaches/ticket/55')
        ->assertOk()
        ->assertJsonCount(3);
});

test('admin can run escalations', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/helpdesk/sla/escalate')
        ->assertOk()
        ->assertJsonStructure(['escalated_count']);
});

test('admin can get compliance stats', function () {
    actingAsUser('admin');

    $this->getJson('/api/v1/helpdesk/sla/stats/compliance')
        ->assertOk()
        ->assertJsonStructure(['total_tickets_checked', 'compliant', 'breached', 'compliance_rate']);
});

test('admin can get performance report', function () {
    actingAsUser('admin');

    $this->getJson('/api/v1/helpdesk/sla/stats/performance')
        ->assertOk()
        ->assertJsonIsArray();
});

test('unauthenticated cannot check breaches', function () {
    auth()->logout();
    $this->postJson('/api/v1/helpdesk/sla/check', [
        'ticket_id' => 1,
        'priority' => 'high',
        'created_at' => now()->toDateTimeString(),
    ])->assertStatus(401);
});

test('unauthenticated cannot run escalations', function () {
    auth()->logout();
    $this->postJson('/api/v1/helpdesk/sla/escalate')->assertStatus(401);
});

test('unauthenticated cannot get compliance stats', function () {
    auth()->logout();
    $this->getJson('/api/v1/helpdesk/sla/stats/compliance')->assertStatus(401);
});
