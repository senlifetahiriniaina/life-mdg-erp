<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Documents\Models\Folder;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FolderResolver
{
    public function folders($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Folder::with(['documents', 'subFolders'])
            ->orderBy('name')
            ->paginate($args['first'] ?? 25);
    }
}
