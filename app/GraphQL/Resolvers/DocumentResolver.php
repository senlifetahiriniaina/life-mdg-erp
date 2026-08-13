<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Documents\Models\Document;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class DocumentResolver
{
    public function documents($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Document::with(['folder', 'owner'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function document($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Document::with(['folder', 'owner'])
            ->findOrFail($args['id']);
    }

    public function createDocument($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $document = Document::create(
            array_merge($args['input'], ['created_by' => auth()->id()])
        );
        return $document->load(['folder', 'owner']);
    }

    public function updateDocument($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $document = Document::findOrFail($args['id']);
        $document->update($args['input']);
        return $document->load(['folder', 'owner']);
    }

    public function deleteDocument($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Document::findOrFail($args['id'])->delete();
        return true;
    }
}
