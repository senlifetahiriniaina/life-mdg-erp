<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\HR\Models\Department;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DepartmentResolver
{
    public function departments($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Department::with(['employees'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function department($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Department::with(['employees'])
            ->findOrFail($args['id']);
    }

    public function createDepartment($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $department = Department::create($args['input']);
        return $department->load(['employees']);
    }

    public function updateDepartment($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $department = Department::findOrFail($args['id']);
        $department->update($args['input']);
        return $department->load(['employees']);
    }

    public function deleteDepartment($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Department::findOrFail($args['id'])->delete();
        return true;
    }
}
