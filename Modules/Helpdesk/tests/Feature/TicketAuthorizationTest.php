<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\Team;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Ticket Authorization', function () {
    beforeEach(function () {
        foreach (['employee', 'support-agent', 'supervisor', 'manager', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->reporter = User::factory()->create();
        $this->assignee = User::factory()->create();
        $this->supervisor = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->admin = User::factory()->create();

        $this->reporter->assignRole('employee');
        $this->assignee->assignRole('support-agent');
        $this->supervisor->assignRole('supervisor');
        $this->manager->assignRole('manager');
        $this->admin->assignRole('admin');

        $this->team = Team::factory()->create();
    });

    test('reporter can view own ticket', function () {
        $ticket = Ticket::factory()->create(['reporter_id' => $this->reporter->id]);

        $this->actingAs($this->reporter, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertOk();
    });

    test('reporter cannot view other tickets', function () {
        $otherReporter = User::factory()->create();
        $ticket = Ticket::factory()->create(['reporter_id' => $otherReporter->id]);

        $this->actingAs($this->reporter, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertForbidden();
    });

    test('support agent can view assigned ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id]);

        $this->actingAs($this->assignee, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertOk();
    });

    test('support agent cannot view unassigned ticket', function () {
        $otherAgent = User::factory()->create();
        $otherAgent->assignRole('support-agent');
        $ticket = Ticket::factory()->create(['assignee_id' => $otherAgent->id]);

        $this->actingAs($this->assignee, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertForbidden();
    });

    test('supervisor can view all tickets', function () {
        $ticket = Ticket::factory()->create(['reporter_id' => $this->reporter->id]);

        $this->actingAs($this->supervisor, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertOk();
    });

    test('manager can view all tickets', function () {
        $ticket = Ticket::factory()->create(['reporter_id' => $this->reporter->id]);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertOk();
    });

    test('admin can view all tickets', function () {
        $ticket = Ticket::factory()->create(['reporter_id' => $this->reporter->id]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertOk();
    });

    test('support agent can update assigned ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id, 'status' => 'open']);

        $this->actingAs($this->assignee, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$ticket->id}", [
                'status' => 'pending',
            ])
            ->assertOk();
    });

    test('support agent cannot close ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id, 'status' => 'pending']);

        $this->actingAs($this->assignee, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$ticket->id}", [
                'status' => 'resolved',
            ])
            ->assertForbidden();
    });

    test('supervisor can close ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id, 'status' => 'pending']);

        $this->actingAs($this->supervisor, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$ticket->id}", [
                'status' => 'resolved',
            ])
            ->assertOk();
    });

    test('manager can close ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id, 'status' => 'pending']);

        $this->actingAs($this->manager, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$ticket->id}", [
                'status' => 'resolved',
            ])
            ->assertOk();
    });

    test('reporter can only update open ticket', function () {
        $openTicket = Ticket::factory()->create(['reporter_id' => $this->reporter->id, 'status' => 'open']);
        $resolvedTicket = Ticket::factory()->create(['reporter_id' => $this->reporter->id, 'status' => 'resolved']);

        $this->actingAs($this->reporter, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$openTicket->id}", ['priority' => 'high'])
            ->assertOk();

        $this->actingAs($this->reporter, 'sanctum')
            ->putJson("/api/v1/helpdesk/tickets/{$resolvedTicket->id}", ['priority' => 'high'])
            ->assertForbidden();
    });

    test('admin can delete ticket', function () {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertNoContent();
    });

    test('support agent cannot delete ticket', function () {
        $ticket = Ticket::factory()->create(['assignee_id' => $this->assignee->id]);

        $this->actingAs($this->assignee, 'sanctum')
            ->deleteJson("/api/v1/helpdesk/tickets/{$ticket->id}")
            ->assertForbidden();
    });
});
