<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\CRM\Models\Account;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class AccountResolver
{
    public function accounts($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Account::with(['contacts', 'opportunities'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function account($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Account::with(['contacts', 'opportunities'])
            ->findOrFail($args['id']);
    }

    public function createAccount($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $account = Account::create($args['input']);
        return $account->load(['contacts', 'opportunities']);
    }

    public function updateAccount($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $account = Account::findOrFail($args['id']);
        $account->update($args['input']);
        return $account->load(['contacts', 'opportunities']);
    }

    public function deleteAccount($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Account::findOrFail($args['id'])->delete();
        return true;
    }
}
