<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\CRM\Models\Contact;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ContactResolver
{
    public function contacts($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $paginator = Contact::with(['account', 'opportunities', 'activities', 'owner'])
            ->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);

        $edges = $paginator->map(fn (Contact $c) => [
            'cursor' => (string) $c->id,
            'node' => $c,
        ])->all();

        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $paginator->hasMorePages(),
                'hasPreviousPage' => $paginator->currentPage() > 1,
                'startCursor' => $edges[0]['cursor'] ?? null,
                'endCursor' => $edges === [] ? null : end($edges)['cursor'],
            ],
        ];
    }

    public function contact($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Contact::with(['account', 'opportunities', 'activities', 'owner'])
            ->findOrFail($args['id']);
    }

    public function createContact($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $contact = Contact::create(
            array_merge($args['input'], ['owner_id' => auth()->id()])
        );

        return $contact->load(['account', 'opportunities', 'activities', 'owner']);
    }

    public function updateContact($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $contact = Contact::findOrFail($args['id']);
        $contact->update($args['input']);

        return $contact->load(['account', 'opportunities', 'activities', 'owner']);
    }

    public function deleteContact($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Contact::findOrFail($args['id'])->delete();
        return true;
    }
}
