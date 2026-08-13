<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Illuminate\Support\Str;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\PurchaseOrderItem;
use Modules\Inventory\Models\StockMovement;

class PurchaseOrderService
{
    public function generateReference(): string
    {
        return 'PO-'.date('Ymd').'-'.strtoupper(Str::random(5));
    }

    public function createPO(array $data, array $items): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            ...$data,
            'reference' => $this->generateReference(),
            'status' => 'draft',
        ]);

        $subtotal = 0.0;
        foreach ($items as $item) {
            $total = (float) $item['quantity_ordered'] * (float) $item['unit_price'];
            $taxAmount = $total * (((float) ($item['tax_rate'] ?? 0)) / 100);
            $subtotal += $total;

            $po->items()->create([
                ...$item,
                'total_price' => round($total + $taxAmount, 4),
                'quantity_received' => 0,
            ]);
        }

        $taxTotal = $po->items()->sum(\DB::raw('total_price - (quantity_ordered * unit_price)'));
        $po->update([
            'subtotal' => round($subtotal, 4),
            'tax_total' => round((float) $taxTotal, 4),
            'grand_total' => round($subtotal + (float) $taxTotal + (float) $po->shipping_cost, 4),
        ]);

        return $po->fresh(['items', 'supplier']);
    }

    public function receive(PurchaseOrder $po, array $receivedQtys): void
    {
        $po->load('items');
        $allReceived = true;
        $anyReceived = false;

        foreach ($receivedQtys as $itemId => $qty) {
            $item = $po->items->find($itemId);
            if (! $item instanceof PurchaseOrderItem) {
                continue;
            }

            $qty = max(0, min((float) $qty, $item->remainingQty()));
            if ($qty <= 0) {
                continue;
            }

            $item->increment('quantity_received', $qty);
            $anyReceived = true;

            // Create inbound stock movement
            if ($item->product_id) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $po->warehouse_id,
                    'type' => 'in',
                    'quantity' => $qty,
                    'reference' => $po->reference,
                    'notes' => "Received from PO {$po->reference}",
                ]);

                Product::where('id', $item->product_id)
                    ->increment('stock_quantity', $qty);
            }
        }

        $po->refresh();
        $allReceived = $po->items->every(
            fn (PurchaseOrderItem $i) => (float) $i->quantity_received >= (float) $i->quantity_ordered
        );

        $po->update([
            'status' => $allReceived ? 'received' : ($anyReceived ? 'partial' : $po->status),
            'received_at' => $allReceived ? now() : null,
        ]);
    }

    public function send(PurchaseOrder $po): void
    {
        $po->update(['status' => 'sent', 'sent_at' => now()]);
    }
}
