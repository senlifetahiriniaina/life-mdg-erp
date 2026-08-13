<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\CRM\Models\Opportunity;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class OpportunityResolver
{
    public function opportunities($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Opportunity::with(['contact', 'account']);

        if (isset($args['stage'])) {
            $query->where('stage', $args['stage']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function opportunity($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Opportunity::with(['contact', 'account'])
            ->findOrFail($args['id']);
    }

    public function createOpportunity($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $opportunity = Opportunity::create($args['input']);
        return $opportunity->load(['contact', 'account']);
    }

    public function updateOpportunity($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $opportunity = Opportunity::findOrFail($args['id']);
        $opportunity->update($args['input']);
        return $opportunity->load(['contact', 'account']);
    }

    public function deleteOpportunity($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Opportunity::findOrFail($args['id'])->delete();
        return true;
    }
}
