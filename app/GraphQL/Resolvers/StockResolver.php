<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Inventory\Models\Stock;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class StockResolver
{
    public function stocks($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Stock::with(['product'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }
}
