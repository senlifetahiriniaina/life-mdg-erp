<?php

declare(strict_types=1);

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;
use Modules\Sales\Models\SalesQuotation;

class SalesService
{
    /**
     * Generate a unique reference for orders.
     */
    private function generateOrderReference(): string
    {
        $year   = now()->format('Y');
        $count  = SalesOrder::whereYear('created_at', $year)->withTrashed()->count() + 1;

        return sprintf('SO-%s-%05d', $year, $count);
    }

    /**
     * Generate a unique reference for quotations.
     */
    private function generateQuotationReference(): string
    {
        $year  = now()->format('Y');
        $count = SalesQuotation::whereYear('created_at', $year)->withTrashed()->count() + 1;

        return sprintf('QT-%s-%05d', $year, $count);
    }

    /**
     * Compute order totals from its lines and persist them.
     */
    private function recalculateOrderTotals(SalesOrder $order): void
    {
        $lines = $order->lines;

        $subtotal       = 0.0;
        $discountAmount = 0.0;
        $taxAmount      = 0.0;

        foreach ($lines as $line) {
            $base      = (float) $line->quantity * (float) $line->unit_price;
            $discount  = $base * ((float) $line->discount_percent / 100);
            $taxBase   = $base - $discount;
            $tax       = $taxBase * ((float) $line->tax_rate / 100);

            $subtotal       += $base;
            $discountAmount += $discount;
            $taxAmount      += $tax;
        }

        $order->update([
            'subtotal'        => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount'      => round($taxAmount, 2),
            'total'           => round($subtotal - $discountAmount + $taxAmount, 2),
        ]);
    }

    /**
     * Create a new sales order with its lines.
     *
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data): SalesOrder {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $order = SalesOrder::create([
                'tenant_id'               => $data['tenant_id'],
                'reference'               => $data['reference'] ?? $this->generateOrderReference(),
                'contact_id'              => $data['contact_id'] ?? null,
                'account_id'              => $data['account_id'] ?? null,
                'opportunity_id'          => $data['opportunity_id'] ?? null,
                'status'                  => 'draft',
                'currency'                => $data['currency'] ?? 'XOF',
                'subtotal'                => 0,
                'discount_amount'         => 0,
                'tax_amount'              => 0,
                'total'                   => 0,
                'notes'                   => $data['notes'] ?? null,
                'shipping_address'        => $data['shipping_address'] ?? null,
                'expected_delivery_date'  => $data['expected_delivery_date'] ?? null,
                'created_by'              => $data['created_by'],
            ]);

            foreach ($lines as $line) {
                $orderLine = new SalesOrderLine([
                    'sales_order_id'   => $order->id,
                    'product_id'       => $line['product_id'] ?? null,
                    'description'      => $line['description'],
                    'quantity'         => $line['quantity'],
                    'unit_price'       => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'tax_rate'         => $line['tax_rate'] ?? 0,
                    'line_total'       => 0,
                ]);
                $orderLine->line_total = $orderLine->computeTotal();
                $orderLine->save();
            }

            $order->load('lines');
            $this->recalculateOrderTotals($order);

            return $order->fresh('lines');
        });
    }

    /**
     * Confirm a sales order (draft → confirmed).
     */
    public function confirmOrder(SalesOrder $order): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw new \RuntimeException("Only draft orders can be confirmed. Current status: {$order->status}");
        }

        $order->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Cancel a sales order with a reason stored in notes.
     */
    public function cancelOrder(SalesOrder $order, string $reason): SalesOrder
    {
        if (! $order->isCancellable()) {
            throw new \RuntimeException("Order in status '{$order->status}' cannot be cancelled.");
        }

        $notes = trim(($order->notes ?? '') . "\n[Cancelled] {$reason}");

        $order->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'notes'        => $notes,
        ]);

        return $order->fresh();
    }

    /**
     * Create a new quotation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createQuotation(array $data): SalesQuotation
    {
        return SalesQuotation::create([
            'tenant_id'   => $data['tenant_id'],
            'reference'   => $data['reference'] ?? $this->generateQuotationReference(),
            'contact_id'  => $data['contact_id'] ?? null,
            'status'      => 'draft',
            'currency'    => $data['currency'] ?? 'XOF',
            'total'       => $data['total'] ?? 0,
            'valid_until' => $data['valid_until'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'created_by'  => $data['created_by'],
        ]);
    }

    /**
     * Convert an accepted quotation to a confirmed sales order.
     *
     * Chantier 19 (Sales re-audit) fix: SalesQuotation has no line items of
     * its own (`sales_quotations` only carries a flat `total`, confirmed via
     * schema+model audit — no lines()/items relation exists anywhere), but
     * createOrder() computes the new order's subtotal/total purely from its
     * `lines` array and ignores any 'total' passed in $data. Since this
     * method never passed a `lines` key at all, every converted quotation
     * silently produced a real order with $0 total and zero line items —
     * empirically confirmed via a real HTTP round trip (a 75000 XOF
     * quotation converted to a 0.00 XOF order with no lines). The quotation
     * has no per-item breakdown to decompose, so the honest fix is a single
     * synthetic line carrying the quotation's own total forward — not a
     * guess, the same total the quotation itself already recorded.
     */
    public function convertQuotationToOrder(SalesQuotation $quotation): SalesOrder
    {
        if (! $quotation->isConvertible()) {
            throw new \RuntimeException("Quotation '{$quotation->reference}' cannot be converted (status: {$quotation->status}).");
        }

        return DB::transaction(function () use ($quotation): SalesOrder {
            $order = $this->createOrder([
                'tenant_id'  => $quotation->tenant_id,
                'contact_id' => $quotation->contact_id,
                'currency'   => $quotation->currency,
                'notes'      => $quotation->notes,
                'created_by' => $quotation->created_by,
                'lines'      => [[
                    'description' => "Devis {$quotation->reference}",
                    'quantity'    => 1,
                    'unit_price'  => (float) $quotation->total,
                ]],
            ]);

            $quotation->update([
                'status'               => 'accepted',
                'converted_to_order_id' => $order->id,
            ]);

            return $order;
        });
    }
}
