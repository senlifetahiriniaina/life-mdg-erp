<?php

declare(strict_types=1);

namespace Tests\Integration\Helpdesk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;
use Modules\Helpdesk\Models\TicketAssignment;
use App\Models\User;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_to_resolution_workflow(): void
    {
        $customer = User::factory()->create();
        $agent = User::factory()->create();

        // Create ticket
        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subject' => 'Cannot login',
            'description' => 'I cannot access my account',
            'status' => 'open',
            'priority' => 'high',
        ]);

        // Assign to agent
        TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'assigned_to_id' => $agent->id,
        ]);

        // Add comment
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'comment' => 'I am investigating your issue',
        ]);

        // Update status
        $ticket->update(['status' => 'in_progress']);

        // Add resolution comment
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'comment' => 'Issue resolved. Your password has been reset.',
        ]);

        // Close ticket
        $ticket->update(['status' => 'closed']);

        $this->assertDatabaseHas('hd_tickets', [
            'id' => $ticket->id,
            'status' => 'closed',
        ]);

        $this->assertEquals(2, TicketComment::where('ticket_id', $ticket->id)->count());
    }

    public function test_ticket_priority_levels(): void
    {
        $priorities = ['low', 'medium', 'high', 'critical'];

        foreach ($priorities as $priority) {
            $ticket = Ticket::factory()->create(['priority' => $priority]);
            $this->assertEquals($priority, $ticket->priority);
        }
    }

    public function test_ticket_status_progression(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $statuses = ['open', 'in_progress', 'waiting_customer', 'closed'];

        foreach ($statuses as $status) {
            $ticket->update(['status' => $status]);
            $this->assertEquals($status, $ticket->refresh()->status);
        }
    }

    public function test_ticket_can_have_multiple_comments(): void
    {
        $ticket = Ticket::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $user1->id, 'comment' => 'First comment']);
        TicketComment::create(['ticket_id' => $ticket->id, 'user_id' => $user2->id, 'comment' => 'Second comment']);

        $comments = TicketComment::where('ticket_id', $ticket->id)->get();

        $this->assertEquals(2, $comments->count());
    }

    public function test_ticket_assignment_tracks_agent(): void
    {
        $ticket = Ticket::factory()->create();
        $agent = User::factory()->create();

        $assignment = TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'assigned_to_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('helpdesk_ticket_assignments', [
            'ticket_id' => $ticket->id,
            'assigned_to_id' => $agent->id,
        ]);
    }

    public function test_ticket_can_be_reassigned(): void
    {
        $ticket = Ticket::factory()->create();
        $agent1 = User::factory()->create();
        $agent2 = User::factory()->create();

        // Initial assignment
        $assignment = TicketAssignment::create([
            'ticket_id' => $ticket->id,
            'assigned_to_id' => $agent1->id,
        ]);

        // Reassign
        $assignment->update(['assigned_to_id' => $agent2->id]);

        $this->assertEquals($agent2->id, $assignment->refresh()->assigned_to_id);
    }

    public function test_ticket_tracks_timestamps(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertNotNull($ticket->created_at);
        $this->assertNotNull($ticket->updated_at);
    }

    public function test_customer_can_have_multiple_tickets(): void
    {
        $customer = User::factory()->create();

        Ticket::factory()->count(3)->create(['customer_id' => $customer->id]);

        $tickets = Ticket::where('customer_id', $customer->id)->get();

        $this->assertEquals(3, $tickets->count());
    }
}
