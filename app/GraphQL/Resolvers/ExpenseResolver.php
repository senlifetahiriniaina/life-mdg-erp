<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Accounting\Models\Expense;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ExpenseResolver
{
    public function expenses($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Expense::with(['employee']);

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        return $query->orderBy('date', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function expense($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Expense::with(['employee'])
            ->findOrFail($args['id']);
    }

    public function createExpense($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $expense = Expense::create($args['input']);
        return $expense->load(['employee']);
    }

    public function updateExpense($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $expense = Expense::findOrFail($args['id']);
        $expense->update($args['input']);
        return $expense->load(['employee']);
    }

    public function deleteExpense($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Expense::findOrFail($args['id'])->delete();
        return true;
    }
}
