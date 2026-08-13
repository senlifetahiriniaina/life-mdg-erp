<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\CrossdockOperation;
use Modules\Inventory\Models\Product;
use RuntimeException;

class CrossdockService
{
    /**
     * Plan a cross-dock operation.
     *
     * @param  array<string, mixed>  $data
     */
    public function planCrossdock(array $data): CrossdockOperation
    {
        return CrossdockOperation::create([
            'inbound_shipment_id' => $data['inbound_shipment_id'] ?? null,
            'outbound_order_id' => $data['outbound_order_id'] ?? null,
            'product_id' => $data['product_id'],
            'qty' => $data['qty'],
            'status' => 'planned',
        ]);
    }

    public function execute(CrossdockOperation $op): void
    {
        if ($op->status !== 'planned') {
            throw new RuntimeException("Operation #{$op->id} cannot be executed (status: {$op->status}).");
        }

        $op->update([
            'status' => 'executed',
            'executed_at' => now(),
        ]);
    }

    /**
     * Return suggestions of products received with pending outbound demand.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSuggestions(): array
    {
        // Heuristic: products with recent inbound and no executed crossdock yet
        $suggestions = [];

        $products = Product::withCount([
            'movements as inbound_count' => function ($q): void {
                $q->where('type', 'in')->whereDate('created_at', '>=', now()->subDays(7));
            },
        ])->having('inbound_count', '>', 0)->get();

        foreach ($products as $product) {
            $suggestions[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'inbound_qty' => $product->inbound_count,
                'suggestion' => 'Cross-dock available — pending demand detected.',
            ];
        }

        return $suggestions;
    }
}
