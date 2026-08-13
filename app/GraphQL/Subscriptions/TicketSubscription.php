<?php

declare(strict_types=1);

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class TicketSubscription extends GraphQLSubscription
{
    /**
     * Check if subscriber is authorized to listen to this subscription
     */
    public function authorize(Subscriber $subscriber, array $args): bool
    {
        return $subscriber->user() !== null;
    }

    /**
     * Filter which subscribers receive the broadcast
     */
    public function filter(Subscriber $subscriber, array $args): bool
    {
        if (isset($args['id'])) {
            // Only send updates for the specific ticket being subscribed to
            return $subscriber->user()->can('view', \Modules\Helpdesk\Models\Ticket::find($args['id']));
        }
        // For ticketCreated, send to all authenticated users
        return true;
    }

    /**
     * Decode the incoming subscription to get resolver arguments
     */
    public static function decode($root): array
    {
        return [
            'id' => $root['ticket_id'] ?? null,
        ];
    }

    /**
     * Get the subscription channel name
     */
    public static function channelName(): string
    {
        return 'tickets';
    }
}
