<?php

declare(strict_types=1);

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class InvoiceSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, array $args): bool
    {
        return $subscriber->user() !== null;
    }

    public function filter(Subscriber $subscriber, array $args): bool
    {
        if (isset($args['id'])) {
            return $subscriber->user()->can('view', \Modules\Accounting\Models\Invoice::find($args['id']));
        }
        return true;
    }

    public static function decode($root): array
    {
        return [
            'id' => $root['invoice_id'] ?? null,
        ];
    }

    public static function channelName(): string
    {
        return 'invoices';
    }
}
