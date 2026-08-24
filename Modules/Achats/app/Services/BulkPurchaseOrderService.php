<?php

namespace Modules\Achats\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\RFQ;

class BulkPurchaseOrderService
{
    protected PurchaseOrderService $poService;

    public function __construct(PurchaseOrderService $poService)
    {
        $this->poService = $poService;
    }

    /**
     * Chantier 32.13 (layer 9 — fake/dead audit): confirmed zero callers
     * anywhere in the app (no controller, route, or test) — a real,
     * self-contained gap in the RFQ workflow, not a broken/duplicate
     * subsystem: SupplierQuoteController::accept()->RFQService::
     * selectWinningQuote() marks a quote 'accepted' and stops there —
     * nothing in the whole app has ever turned an accepted quote into a
     * real PurchaseOrder. Activated: the method itself had 2 real bugs
     * that would have made a wired-up call produce a broken PO — never
     * setting company_id at all (the created PO would be invisible to the
     * very company that created it, failing every assertSameCompany()
     * check in this module) and hardcoding 'USD'/'pcs' instead of the real
     * RFQ's currency and each line's real unit.
     */
    public function createPOsFromRFQ(RFQ $rfq, User $createdBy): Collection
    {
        $rfq->load(['lines', 'quotes']);

        $acceptedQuotes = $rfq->quotes()
            ->where('status', 'accepted')
            ->get()
            ->groupBy('supplier_id');

        $purchaseOrders = collect();

        foreach ($acceptedQuotes as $supplierId => $quotes) {
            $quote = $quotes->first();

            $poData = [
                'supplier_id' => $supplierId,
                'order_date' => now()->toDateString(),
                'delivery_date' => now()->addDays($quote->delivery_days ?? 0)->toDateString(),
                'currency' => $rfq->currency ?? config('achats.default_currency', 'USD'),
                'created_by' => $createdBy->id,
                'company_id' => $rfq->company_id,
            ];

            $po = $this->poService->createPurchaseOrder($poData);

            // Add line items based on RFQ lines
            foreach ($rfq->lines as $rfqLine) {
                $lineData = [
                    'product_id' => $rfqLine->product_id,
                    'description' => $rfqLine->description,
                    'quantity' => $rfqLine->quantity,
                    'unit' => $rfqLine->unit ?? 'pcs',
                    'unit_price' => $quote->unit_price,
                    'tax_rate' => 0,
                ];

                $this->poService->addLineItem($po, $lineData);
            }

            // Calculate and update totals
            $totals = $this->poService->calculateTotals($po);
            $po->update([
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'] + ($po->shipping_cost ?? 0),
            ]);

            $purchaseOrders->push($po);
        }

        return $purchaseOrders;
    }

    public function createBulkPOsFromSuppliers(array $supplierIds, array $lineItems, User $createdBy): Collection
    {
        $purchaseOrders = collect();

        foreach ($supplierIds as $supplierId) {
            $poData = [
                'supplier_id' => $supplierId,
                'order_date' => now()->toDateString(),
                'delivery_date' => now()->addDays(7)->toDateString(),
                'currency' => 'USD',
                'created_by' => $createdBy->id,
            ];

            $po = $this->poService->createPurchaseOrder($poData);

            foreach ($lineItems as $item) {
                $this->poService->addLineItem($po, $item);
            }

            $totals = $this->poService->calculateTotals($po);
            $po->update([
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'] + ($po->shipping_cost ?? 0),
            ]);

            $purchaseOrders->push($po);
        }

        return $purchaseOrders;
    }

    public function duplicatePurchaseOrder(PurchaseOrder $source, User $createdBy): PurchaseOrder
    {
        $source->load('lines');

        $newPo = $this->poService->createPurchaseOrder([
            'supplier_id' => $source->supplier_id,
            'order_date' => now()->toDateString(),
            'delivery_date' => $source->delivery_date,
            'currency' => $source->currency,
            'shipping_cost' => $source->shipping_cost,
            'created_by' => $createdBy->id,
        ]);

        foreach ($source->lines as $line) {
            $this->poService->addLineItem($newPo, [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'tax_rate' => $line->tax_rate,
            ]);
        }

        $totals = $this->poService->calculateTotals($newPo);
        $newPo->update([
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'] + ($newPo->shipping_cost ?? 0),
        ]);

        return $newPo;
    }
}
