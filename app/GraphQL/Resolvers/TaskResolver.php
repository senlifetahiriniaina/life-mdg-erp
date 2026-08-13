<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Projects\Models\Task;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class TaskResolver
{
    public function tasks($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Task::with(['project', 'assignee']);

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function task($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Task::with(['project', 'assignee'])
            ->findOrFail($args['id']);
    }

    public function createTask($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $task = Task::create($args['input']);
        return $task->load(['project', 'assignee']);
    }

    public function updateTask($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $task = Task::findOrFail($args['id']);
        $task->update($args['input']);
        return $task->load(['project', 'assignee']);
    }

    public function deleteTask($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Task::findOrFail($args['id'])->delete();
        return true;
    }
}
