<?php

declare(strict_types=1);

use Carbon\Carbon;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Services\SlaService;


// ─── Tests ────────────────────────────────────────────────────────────────────

it('applies an SLA policy and sets sla_due_at', function () {
    $ticket = Ticket::factory()->create(['sla_due_at' => null]);
    $policy = SlaPolicy::factory()->create([
        'resolution_time_minutes' => 240,
        'business_hours' => null,
    ]);

    $before = $ticket->created_at;
    $service = new SlaService;
    $service->apply($ticket, $policy);
    $after = $ticket->created_at;

    $ticket->refresh();

    expect($ticket->sla_id)->toBe($policy->id);
    expect($ticket->sla_due_at)->not->toBeNull();
    expect($ticket->sla_breached)->toBeFalse();

    // sla_due_at should be approximately 4 hours after the ticket's creation
    // (the resolution clock, not the response clock — within a 1-minute window)
    $expectedMin = $before->copy()->addHours(4)->subMinute();
    $expectedMax = $after->copy()->addHours(4)->addMinute();

    expect($ticket->sla_due_at->between($expectedMin, $expectedMax))->toBeTrue();
});

it('respects business hours when calculating due date', function () {
    // Start: Friday at 16:00, 8 business hours to consume.
    // Business hours: Mon–Fri 09:00–18:00.
    //
    // Friday 16:00 → 2 hours available → remaining = 6
    // Roll to next work day: Monday 09:00
    // Monday 09:00 → add 6 hours → Monday 15:00
    $businessHours = [
        'days' => [1, 2, 3, 4, 5],
        'start' => '09:00',
        'end' => '18:00',
    ];

    // Pin a Friday at exactly 16:00 UTC
    $friday = Carbon::parse('next friday')->setTime(16, 0, 0);

    Carbon::setTestNow($friday);

    $ticket = Ticket::factory()->create(['sla_due_at' => null]);
    $policy = SlaPolicy::factory()->create([
        'resolution_time_minutes' => 480,
        'business_hours' => $businessHours,
    ]);

    $service = new SlaService;
    $service->apply($ticket, $policy);

    Carbon::setTestNow(); // reset mock

    $ticket->refresh();

    // Expected: the following Monday at 15:00
    $expectedMonday = $friday->copy()->addDays(3)->setTime(15, 0, 0);

    expect($ticket->sla_due_at)->not->toBeNull();
    expect($ticket->sla_due_at->toDateTimeString())
        ->toBe($expectedMonday->toDateTimeString());
});

it('detects and marks breached tickets', function () {
    // Ticket whose SLA due date is in the past and not yet marked as breached
    $breachedTicket = Ticket::factory()->create([
        'sla_due_at' => now()->subHour(),
        'sla_breached' => false,
        'status' => 'open',
    ]);

    // Ticket that is not due yet — should remain untouched
    $safeTicket = Ticket::factory()->create([
        'sla_due_at' => now()->addHour(),
        'sla_breached' => false,
        'status' => 'open',
    ]);

    $service = new SlaService;
    $count = $service->checkBreaches();

    expect($count)->toBe(1);

    expect($breachedTicket->fresh()->sla_breached)->toBeTrue();
    expect($safeTicket->fresh()->sla_breached)->toBeFalse();
});

it('does not mark resolved tickets as breached', function () {
    $resolvedTicket = Ticket::factory()->create([
        'sla_due_at' => now()->subHour(),
        'sla_breached' => false,
        'status' => 'resolved',
    ]);

    $service = new SlaService;
    $count = $service->checkBreaches();

    expect($count)->toBe(0);
    expect($resolvedTicket->fresh()->sla_breached)->toBeFalse();
});

it('seeds exactly one default policy among the 4 tiers', function () {
    $service = new SlaService;
    $policies = $service->seedDefaultPolicies();

    expect($policies)->toHaveCount(4);
    expect(SlaPolicy::where('is_default', true)->count())->toBe(1);
    expect(SlaPolicy::where('is_default', true)->first()->priority)->toBe('medium');
});

it('seeding default policies twice does not create duplicates', function () {
    $service = new SlaService;
    $service->seedDefaultPolicies();
    $service->seedDefaultPolicies();

    expect(SlaPolicy::count())->toBe(4);
});
