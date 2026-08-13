<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Accounting\Models\Bill;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class BillResolver
{
    public function bills($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Bill::with(['vendor'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function bill($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Bill::with(['vendor'])
            ->findOrFail($args['id']);
    }
}
