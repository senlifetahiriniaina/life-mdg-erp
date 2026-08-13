<?php

declare(strict_types=1);

namespace App\GraphQL\Resolvers;

use Modules\Accounting\Models\Invoice;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class InvoiceResolver
{
    public function invoices($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $query = Invoice::with(['customer', 'lineItems']);

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($args['first'] ?? 25);
    }

    public function invoice($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        return Invoice::with(['customer', 'lineItems'])
            ->findOrFail($args['id']);
    }

    public function createInvoice($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $invoice = Invoice::create(
            array_merge($args['input'], ['created_by' => auth()->id()])
        );
        return $invoice->load(['customer', 'lineItems']);
    }

    public function updateInvoice($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        $invoice = Invoice::findOrFail($args['id']);
        $invoice->update($args['input']);
        return $invoice->load(['customer', 'lineItems']);
    }

    public function deleteInvoice($root, array $args, GraphQLContext $context, ResolveInfo $info)
    {
        Invoice::findOrFail($args['id'])->delete();
        return true;
    }
}
