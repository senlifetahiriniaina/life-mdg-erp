<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Helpdesk\Models\Ticket;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TicketResolver
{
    public function tickets($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Ticket::with(['reporter', 'assignee', 'comments']);

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function ticket($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Ticket::with(['reporter', 'assignee', 'comments'])
            ->findOrFail($args['id']);
    }

    public function createTicket($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $ticket = Ticket::create(
            array_merge($args['input'], ['reporter_id' => auth()->id()])
        );
        return $ticket->load(['reporter', 'assignee', 'comments']);
    }

    public function updateTicket($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $ticket = Ticket::findOrFail($args['id']);
        $ticket->update($args['input']);
        return $ticket->load(['reporter', 'assignee', 'comments']);
    }

    public function closeTicket($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $ticket = Ticket::findOrFail($args['id']);
        $ticket->update(['status' => 'CLOSED', 'resolved_at' => now()]);
        return true;
    }
}
