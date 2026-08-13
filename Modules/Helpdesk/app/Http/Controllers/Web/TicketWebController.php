<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Models\Ticket;

class TicketWebController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()
            ->with(['team', 'assignee'])
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%");
            }))
            ->paginate(25)->withQueryString();

        return Inertia::render('Helpdesk/Tickets/Index', ['tickets' => $tickets]);
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load(['comments.user', 'team', 'assignee', 'reporter']);

        return Inertia::render('Helpdesk/Tickets/Show', ['ticket' => $ticket]);
    }
}
