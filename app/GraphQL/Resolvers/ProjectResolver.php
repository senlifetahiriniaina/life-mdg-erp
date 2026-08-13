<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Projects\Models\Project;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ProjectResolver
{
    public function projects($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Project::with(['teamMembers', 'tasks']);

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function project($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Project::with(['teamMembers', 'tasks'])
            ->findOrFail($args['id']);
    }

    public function createProject($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $project = Project::create($args['input']);
        return $project->load(['teamMembers', 'tasks']);
    }

    public function updateProject($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $project = Project::findOrFail($args['id']);
        $project->update($args['input']);
        return $project->load(['teamMembers', 'tasks']);
    }

    public function deleteProject($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Project::findOrFail($args['id'])->delete();
        return true;
    }
}
