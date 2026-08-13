<?php

declare(strict_types=1);

namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class ProjectSubscription extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, array $args): bool
    {
        return $subscriber->user() !== null;
    }

    public function filter(Subscriber $subscriber, array $args): bool
    {
        if (isset($args['id']) || isset($args['projectId'])) {
            $projectId = $args['id'] ?? $args['projectId'];
            return $subscriber->user()->can('view', \Modules\Projects\Models\Project::find($projectId));
        }
        return true;
    }

    public static function decode($root): array
    {
        return [
            'id' => $root['id'] ?? null,
            'projectId' => $root['project_id'] ?? null,
        ];
    }

    public static function channelName(): string
    {
        return 'projects';
    }
}
