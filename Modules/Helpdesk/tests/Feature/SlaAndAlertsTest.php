<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Services\TicketAssignmentService;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['support-agent', 'manager'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

describe('SLA Management', function () {
    beforeEach(function () {
        $this->assignee = User::factory()->create();
        $this->assignee->assignRole('support-agent');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');
    });

    test('default SLA policy is applied to new ticket', function () {
        $defaultSla = SlaPolicy::factory()->create([
            'is_default' => true,
            'response_time_minutes' => 240,
            'resolution_time_minutes' => 1440,
        ]);

        $ticket = Ticket::factory()->create();

        expect($ticket->sla_id)->toBe($defaultSla->id);
        expect($ticket->sla_due_at)->not->toBeNull();
    });

    test('ticket SLA due date is calculated correctly', function () {
        $sla = SlaPolicy::factory()->create([
            'response_time_minutes' => 120,
            'resolution_time_minutes' => 1440,
        ]);

        $createdAt = now()->startOfDay();
        $ticket = Ticket::factory()->create([
            'sla_id' => $sla->id,
            'created_at' => $createdAt,
        ]);

        $expectedDue = $createdAt->addMinutes($sla->resolution_time_minutes);
        expect($ticket->sla_due_at->timestamp)->toBe($expectedDue->timestamp);
    });

    test('SLA can be manually overridden', function () {
        $newSla = SlaPolicy::factory()->create();
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->manager, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$ticket->id}", [
                'sla_id' => $newSla->id,
            ])
            ->assertOk();

        $ticket->refresh();
        expect($ticket->sla_id)->toBe($newSla->id);
    });

    test('multiple SLA policies can coexist', function () {
        $sla1 = SlaPolicy::factory()->create(['priority' => 'critical', 'response_time_minutes' => 60]);
        $sla2 = SlaPolicy::factory()->create(['priority' => 'high', 'response_time_minutes' => 240]);
        $sla3 = SlaPolicy::factory()->create(['priority' => 'low', 'response_time_minutes' => 1440]);

        expect(SlaPolicy::count())->toBeGreaterThanOrEqual(3);
    });
});

// Chantier 32.21: the "SLA Alerts" describe block that used to live here
// exercised Modules\Helpdesk\Services\AlertService directly — confirmed via
// exhaustive grep to be a fully dead duplicate of the real, live, scheduled
// SlaAutomationService/SlaService breach-checking engine (checkSlaBreaches()
// duplicated the same "mark overdue tickets sla_breached=true" concept, and
// notifyTicketAssignment()/checkSlaBreachWarnings()/checkResponseReminders()/
// getPendingAlerts() were all log-only or otherwise never called from any
// real controller/route/job/command anywhere in the app — only from this
// isolated test file, the exact "isolated unit test masking dead code"
// pattern already documented repeatedly elsewhere in this session). Deleted
// alongside the service; the real SLA-breach coverage lives in
// SlaAutomationTest.php/TicketServiceSlaTest.php against the actually-live
// SlaController/SlaAutomationService path.

describe('Ticket Assignment', function () {
    beforeEach(function () {
        $this->assignmentService = new TicketAssignmentService();
        $this->team = \Modules\Helpdesk\Models\Team::factory()->create();

        $this->agent1 = User::factory()->create();
        $this->agent1->assignRole('support-agent');
        $this->team->members()->attach($this->agent1);

        $this->agent2 = User::factory()->create();
        $this->agent2->assignRole('support-agent');
        $this->team->members()->attach($this->agent2);
    });

    test('round-robin assignment distributes tickets', function () {
        $ticket1 = Ticket::factory()->create(['team_id' => $this->team->id]);
        $ticket2 = Ticket::factory()->create(['team_id' => $this->team->id]);

        $this->assignmentService->assignRoundRobin($ticket1, $this->team);
        $this->assignmentService->assignRoundRobin($ticket2, $this->team);

        $ticket1->refresh();
        $ticket2->refresh();

        expect($ticket1->assignee_id)->not->toBeNull();
        expect($ticket2->assignee_id)->not->toBeNull();
    });

    test('assign ticket to specific agent', function () {
        $ticket = Ticket::factory()->create(['team_id' => $this->team->id]);

        $result = $this->assignmentService->assignToAgent($ticket, $this->agent1);

        expect($result)->toBeTrue();
        $ticket->refresh();
        expect($ticket->assignee_id)->toBe($this->agent1->id);
    });

    test('unassign ticket from agent', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->agent1->id]);

        $result = $this->assignmentService->unassign($ticket);

        expect($result)->toBeTrue();
        $ticket->refresh();
        expect($ticket->assignee_id)->toBeNull();
    });

    test('get agent load statistics', function () {
        Ticket::factory()->create(['assignee_id' => $this->agent1->id, 'status' => 'open']);
        Ticket::factory()->create(['assignee_id' => $this->agent1->id, 'status' => 'pending']);

        $load = $this->assignmentService->getAgentLoad($this->agent1);

        expect($load)->toHaveKeys(['total_tickets', 'open_tickets', 'pending_tickets', 'sla_breached', 'sla_warnings']);
        expect($load['total_tickets'])->toBe(2);
    });
});
