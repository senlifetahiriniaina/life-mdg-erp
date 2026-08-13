<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\HR\Models\Employee;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class EmployeeResolver
{
    public function employees($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Employee::with(['department', 'trainings'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function employee($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Employee::with(['department', 'trainings'])
            ->findOrFail($args['id']);
    }

    public function createEmployee($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $employee = Employee::create($args['input']);
        return $employee->load(['department', 'trainings']);
    }

    public function updateEmployee($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $employee = Employee::findOrFail($args['id']);
        $employee->update($args['input']);
        return $employee->load(['department', 'trainings']);
    }

    public function deleteEmployee($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Employee::findOrFail($args['id'])->delete();
        return true;
    }
}
