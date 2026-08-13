<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\Rma;
use RuntimeException;

class RmaService
{
    /**
     * Create a new RMA.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Rma
    {
        /** @var Rma $rma */
        $rma = Rma::create([
            'reference' => $data['reference'] ?? 'RMA-'.strtoupper(uniqid()),
            'order_id' => $data['order_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'reason' => $data['reason'],
            'status' => 'requested',
            'items' => $data['items'],
            'return_method' => $data['return_method'] ?? 'refund',
        ]);

        return $rma;
    }

    public function approve(Rma $rma): void
    {
        if ($rma->status !== 'requested') {
            throw new RuntimeException("RMA #{$rma->id} cannot be approved (status: {$rma->status}).");
        }

        $rma->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function receive(Rma $rma): void
    {
        if ($rma->status !== 'approved') {
            throw new RuntimeException("RMA #{$rma->id} cannot be received (status: {$rma->status}).");
        }

        $rma->update([
            'status' => 'received',
            'received_at' => now(),
        ]);
    }

    public function processRefund(Rma $rma, array $refundData = []): void
    {
        if (! in_array($rma->status, ['received', 'inspected'], true)) {
            throw new RuntimeException("RMA #{$rma->id} cannot be refunded (status: {$rma->status}).");
        }

        // Prevent double refunds
        if ($rma->refunded_at !== null) {
            throw new RuntimeException("RMA #{$rma->id} has already been refunded.");
        }

        // Validate refund amount if provided
        if (! empty($refundData['amount'])) {
            $this->validateRefundAmount($rma, (float) $refundData['amount']);
        }

        // Validate that refunded items match RMA items
        if (! empty($refundData['items'])) {
            $this->validateRefundItems($rma, $refundData['items']);
        }

        $rma->update([
            'status' => 'refunded',
            'refunded_at' => now(),
        ]);
    }

    /**
     * Validate that the refund amount is valid for the RMA.
     * Ensures refund doesn't exceed original order amount.
     */
    private function validateRefundAmount(Rma $rma, float $refundAmount): void
    {
        if ($refundAmount <= 0) {
            throw new RuntimeException('Refund amount must be greater than zero.');
        }

        // Calculate total amount from RMA items
        $totalAmount = 0.0;
        if (! empty($rma->items) && is_array($rma->items)) {
            foreach ($rma->items as $item) {
                $itemTotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $totalAmount += $itemTotal;
            }
        }

        if ($refundAmount > $totalAmount) {
            throw new RuntimeException(
                "Refund amount ({$refundAmount}) exceeds RMA total ({$totalAmount})."
            );
        }
    }

    /**
     * Validate that refunded items match the items in the RMA.
     * Prevents unauthorized items from being refunded.
     */
    private function validateRefundItems(Rma $rma, array $refundItems): void
    {
        if (empty($rma->items) || ! is_array($rma->items)) {
            throw new RuntimeException('RMA has no items to refund.');
        }

        $rmaItemIds = array_column($rma->items, 'id');

        foreach ($refundItems as $item) {
            $itemId = $item['id'] ?? null;
            $quantity = $item['quantity'] ?? 0;

            if (! $itemId || $quantity <= 0) {
                throw new RuntimeException('Invalid refund item: missing id or quantity.');
            }

            if (! in_array($itemId, $rmaItemIds, true)) {
                throw new RuntimeException("Item {$itemId} is not part of this RMA.");
            }

            // Validate quantity doesn't exceed RMA quantity
            $rmaItem = collect($rma->items)->firstWhere('id', $itemId);
            if ($rmaItem && ($quantity > ($rmaItem['quantity'] ?? 0))) {
                throw new RuntimeException(
                    "Cannot refund {$quantity} units of item {$itemId} (available: {$rmaItem['quantity']})."
                );
            }
        }
    }
}
