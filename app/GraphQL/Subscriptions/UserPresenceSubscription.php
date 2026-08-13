<?php

declare(strict_types=1);

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class UserPresenceSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, array $args): bool
    {
        return $subscriber->user() !== null;
    }

    public function filter(Subscriber $subscriber, array $args): bool
    {
        // Send presence updates to all authenticated users
        return true;
    }

    public static function decode($root): array
    {
        return [];
    }

    public static function channelName(): string
    {
        return 'presence';
    }
}
