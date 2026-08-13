<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Inventory\Models\Product;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ProductResolver
{
    public function products($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Product::with(['stock'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function product($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Product::with(['stock'])
            ->findOrFail($args['id']);
    }

    public function createProduct($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $product = Product::create($args['input']);
        return $product->load(['stock']);
    }

    public function updateProduct($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $product = Product::findOrFail($args['id']);
        $product->update($args['input']);
        return $product->load(['stock']);
    }

    public function deleteProduct($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Product::findOrFail($args['id'])->delete();
        return true;
    }
}
