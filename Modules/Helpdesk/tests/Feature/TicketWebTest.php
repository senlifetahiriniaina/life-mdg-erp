<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Tests\Feature;

use App\Models\User;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketComment;
use Tests\TestCase;

/**
 * Tickets/Show.vue had a real web route and controller passing a real
 * `ticket` prop, but the page never declared defineProps() — it rendered
 * entirely hardcoded mock data (a fake ticket #1041 about Orange Money)
 * regardless of which real ticket a user navigated to.
 */
class TicketWebTest extends TestCase
{
    public function test_show_renders_with_real_ticket_data()
    {
        $user = User::factory()->create();
        $commenter = User::factory()->create();
        $ticket = Ticket::factory()->create(['subject' => 'Real ticket subject', 'status' => 'open']);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $commenter->id, 'is_internal' => false]);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $commenter->id, 'is_internal' => true]);

        $response = $this->actingAs($user)->get("/helpdesk/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Helpdesk/Tickets/Show', false)
            ->where('ticket.id', $ticket->id)
            ->where('ticket.subject', 'Real ticket subject')
            ->has('ticket.comments', 2)
        );
    }
}
